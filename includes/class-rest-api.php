<?php
/**
 * REST API endpoint for election results.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a public REST endpoint at /wp-json/zwgr26/v1/uitslagen
 * returning election results for all municipalities.
 */
class Rest_API {

	/**
	 * Data provider.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Constructor.
	 *
	 * @param Data_Provider $data Data provider.
	 */
	public function __construct( Data_Provider $data ) {
		$this->data = $data;
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Register the uitslagen route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			'zwgr26/v1',
			'/uitslagen',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_uitslagen' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle the uitslagen request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_uitslagen(): \WP_REST_Response {
		$results = $this->data->get_election_results();
		$output  = [];

		foreach ( $results as $slug => $entry ) {
			$partijen = [];
			foreach ( $entry['partijen'] as $partij ) {
				$partijen[] = [
					'naam'        => $partij['naam'],
					'kleur'       => $partij['kleur'],
					'zetels_2022' => $partij['zetels_2022'],
					'zetels_2026' => $partij['zetels'],
				];
			}

			$output[ $slug ] = [
				'naam'          => $entry['naam'],
				'totaal_zetels' => $entry['totaal_zetels'],
				'has_2026'      => $entry['has_2026'],
				'opkomst_2022'  => $entry['opkomst_2022'],
				'opkomst_2026'  => $entry['opkomst_2026'],
				'partijen'      => $partijen,
			];
		}

		return new \WP_REST_Response( $output, 200 );
	}
}
