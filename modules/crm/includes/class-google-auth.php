<?php

namespace WeDevs\ERP\CRM;

class Google_Auth {

    /**
     * @var \Google_Client
     */
    private $client;
    private $options;

    public function __construct() {
        //check if options are saved
        //get options and set
        //init client with options
        $this->init_client();
        add_action( 'admin_init', [ $this, 'handle_google_auth' ] );
    }

    /**
     * Initializes the WeDevs_ERP() class
     *
     * Checks for an existing WeDevs_ERP() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( !$instance ) {
            $instance = new self();
        }

        return $instance;
    }

    private function init_client() {
        $creds = $this->has_credentials();
        if ( empty($creds) ) {
            return false;
        }

        $client = new \Google_Client( array(
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'redirect_uris' => array(
                $this->get_redirect_url(),
            ),
        ) );

        $client->setAccessType( "offline" );        // offline access
        $client->setIncludeGrantedScopes( true );   // incremental auth
        $client->addScope( \Google_Service_Gmail::GMAIL_SEND );
        $client->addScope( \Google_Service_Gmail::GMAIL_MODIFY );
        $client->addScope( \Google_Service_Gmail::GMAIL_SETTINGS_BASIC );
        $client->addScope( \Google_Service_Gmail::GMAIL_READONLY );
        $client->setRedirectUri( $this->get_redirect_url() );

        $token = get_option( 'erp_google_access_token' );

        if ( !empty( $token ) ) {
            $client->setAccessToken( $token );
        }

        $this->client = $client;

    }

    public function get_client() {
        if ( !$this->client instanceof \Google_Client ) {
            $this->init_client();
        }
        return $this->client;
    }

    public function set_access_token( $code ) {
        $this->client->authenticate( $code );
        $access_token = $this->client->getAccessToken();
        update_option( 'erp_google_access_token', $access_token );
    }

    public function get_redirect_url() {
        return add_query_arg( 'erp-auth', 'google', admin_url( 'options-general.php' ) );
    }

    public function is_active() {
        if ( $this->has_credentials() && $this->is_connected() ){
            return true;
        }
        return false;
    }

    public function has_credentials() {
        $options = get_option( 'erp_settings_erp-email_gmail_api', [] );

        if ( !isset( $options['client_id'] ) || empty( $options['client_id'] ) ) {
            return false;
        }

        if ( !isset( $options['client_secret'] ) || empty( $options['client_secret'] ) ) {
            return false;
        }

        return $options;
    }

    public function is_connected() {
        $email = get_option( 'erp_gmail_authenticated_email', '' );

        if ( !empty( $email ) ) {
            return $email;
        }

        return false;
    }

    public function handle_google_auth() {
        if ( !isset( $_GET['erp-auth'] ) || !isset( $_GET['code'] ) ) {
            return;
        }
        $this->set_access_token( $_GET['code'] );

        wperp()->google_sync->update_profile();

        $settings_url = add_query_arg( [ 'page' => 'erp-settings', 'tab' => 'erp-email' ], admin_url( 'admin.php' ) );
        wp_redirect( $settings_url );
    }

}