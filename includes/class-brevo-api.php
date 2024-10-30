<?php

defined( 'ABSPATH' ) || exit;

class PCAFE_GFBR_Api_Free {
    protected $api_key = null;

    public function __construct( $api_key = null ) {
		$this->api_key = $api_key;
	}

    protected function make_request( $route = '', $body = array(), $method = 'GET', $response_code = 200 ) {

        if( 'GET' !== $method ) {
		    $body = wp_json_encode( $body );
        }

		// Construct headers.
		$headers = array(
			'Content-Type' => 'application/json',
			'api-key'      => $this->api_key,
		);

        $args = array(
            'timeout' => 15,
            'body'    => $body,
            'method'  => $method,
            'headers' => $headers,
        );

        $request_url = 'https://api.brevo.com/v3/' . $route;

        $result = wp_remote_request( $request_url, $args );

        $response = wp_remote_retrieve_body( $result );
        $response = GFCommon::maybe_decode_json($response);
        $api_response_code = (int) wp_remote_retrieve_response_code( $result );

        if( $api_response_code !== $response_code && is_array( $response ) ) {

            $wp_error = new WP_Error( $api_response_code, $response['errors'][0]['detail'] );

            return $wp_error;
        }

        return $response;
    }


    public function get_account() {
        return $this->make_request("account");
    }

    public function get_lists( $limit = 30, $sort = 'asc' ) {
        $response = $this->make_request(
            'contacts/lists/',
            array(
                'limit'  => $limit,
                'offset' => 0,
                'sort'   => $sort,
            ),
            'GET'
        );

        return $response;
    }

    public function create_contact( $parameters) {
        return $this->make_request('contacts', $parameters, 'POST', 201);
    }
}