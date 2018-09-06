<?php

/*
Plugin Name: Inner reference checker.
Description: Plugin allow config and check count of inner reference in post.
Version: 1.0
Author: Maxim Rudenko
Author URI: http://cabinet.sumdu.edu.ua/person/card/9lz1lb4O
*/

add_action( 'plugins_loaded', array( 'rms_inner_reference', 'instance' ) );


class rms_inner_reference {
    private $post_id;
    private $transient_name;
    private $current_user;
    private static $instance = null;
    private const COUNT_INNER_REFERENCE = 10;

    private function __construct()
    {
        if ( ! is_admin() )
            return;

        add_filter( 'wp_insert_post_data', array( $this, 'rms_check_inner_reference' ), 99, 2 );
        add_action( 'post_submitbox_start', array($this, 'rms_submitbox_start_handler') );
        add_action('admin_menu', array($this, 'rms_inner_reference_menu') );
        add_action('admin_init', array($this, 'rms_inner_reference_settings'));


        $this->post_id = isset( $_GET[ 'post' ] ) ? intval( $_GET[ 'post' ] ) : 0;
        $this->current_user = get_current_user_id();
        // key should be specific to post and the user editing the post
        $this->transient_name = "save_post_error_{$this->post_id}_{$this->current_user}";

    }

    /**
     * Singleton factory method.
     * @return null|rms_inner_reference
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function rms_inner_reference_menu() {
        add_options_page( 'Options inner reference', 'Inner reference', 'manage_options', 'rms-inner-reference-options', array($this,'rms_options_page'));
    }

    public function rms_inner_reference_settings() {
        //print_r(get_option('rms_inner_reference_options'));
        //exit;
        register_setting ( 'rms_inner_reference_options_group', 'rms_inner_reference_options', array($this, 'rms_inner_reference_sanitize') );

        // $id, $title, $callback, $page
        add_settings_section('rms_inner_reference_options_id', '', '', 'rms-inner-reference-options');
        // $id, $title, $callback, $page, $section, $args
        add_settings_field('rms_inner_reference_count', 'Count inner reference', array($this, 'rms_inner_reference_count_cb'), 'rms-inner-reference-options', 'rms_inner_reference_options_id', array('label_for' => 'rms_inner_reference_count') );

        // $id, $title, $callback, $page
        add_settings_section('rms_inner_reference_post_type_id', 'Type post', '', 'rms-inner-reference-options');
        add_settings_field('rms_inner_reference_post_type', 'Post', array($this, 'rms_inner_reference_post_type_cb'), 'rms-inner-reference-options', 'rms_inner_reference_post_type_id', array( 'label_for' => 'rms_inner_reference_post_type') );
        add_settings_field('rms_inner_reference_page_type', 'Page', array($this, 'rms_inner_reference_page_type_cb'), 'rms-inner-reference-options', 'rms_inner_reference_post_type_id', array( 'label_for' => 'rms_inner_reference_page_type') );
    }

    function rms_inner_reference_sanitize($options) {
        $clear_options = array();
        if ( !filter_var($options['rms_inner_reference_count'], FILTER_VALIDATE_INT) ) {
            $clear_options['rms_inner_reference_count'] = self::COUNT_INNER_REFERENCE;
        } else {
            $clear_options['rms_inner_reference_count'] = $options['rms_inner_reference_count'];
        }

        if ( !isset($options['rms_inner_reference_post_type']) ) {
            $clear_options['rms_inner_reference_post_type'] = 0;
        } else {
            $clear_options['rms_inner_reference_post_type'] = 1;
        }

        if ( !isset($options['rms_inner_reference_page_type']) ) {
            $clear_options['rms_inner_reference_page_type'] = 0;
        } else {
            $clear_options['rms_inner_reference_page_type'] = 1;
        }

        return $clear_options;
    }

    public function rms_inner_reference_count_cb() {
        $options = get_option('rms_inner_reference_options');?>
        <input type="text" name="rms_inner_reference_options[rms_inner_reference_count]" id="rms_inner_reference_count" value="<?=$options['rms_inner_reference_count']?>"  class="regular-text">
        <?php
    }

    public function rms_inner_reference_post_type_cb() {
        $options = get_option('rms_inner_reference_options');?>
        <input type="checkbox" name="rms_inner_reference_options[rms_inner_reference_post_type]" id="rms_inner_reference_post_type" value="1" <?php checked(1,$options['rms_inner_reference_post_type']); ?> class="regular-text">
        <?php
    }

    public function rms_inner_reference_page_type_cb() {
        $options = get_option('rms_inner_reference_options');?>
        <input type="checkbox" name="rms_inner_reference_options[rms_inner_reference_page_type]" id="rms_inner_reference_page_type" value="1" <?php checked(1,$options['rms_inner_reference_page_type']); ?>  class="regular-text">
        <?php
    }

    public function rms_options_page () {
        ?>
        <div class="wrap">
            <h2>Options inner reference</h2>
            <form action="options.php" method="post">
                <?php settings_fields('rms_inner_reference_options_group'); ?>
                <?php do_settings_sections('rms-inner-reference-options'); ?>
                <?php submit_button( ) ?>
            </form>
        </div>
        <?php
    }


    /**
     * Runs validation for inner reference.
     *
     * @param $data array 	Post data array
     * @param $postarr 		The $_POST array
     * @return $data 		The final post data array to be inserted/updated
     */
    public function rms_check_inner_reference( $data, $postarr ) {

        $post_id = isset( $postarr[ 'ID' ] ) ? $postarr[ 'ID' ] : false;
        //$post_id = isset( $postarr[ 'post_ID' ] ) ? $postarr[ 'post_ID' ] : false;

        if ( ! $post_id )
            return $data;

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $data;

        $this->transient_name = "save_post_error_{$post_id}_{$this->current_user}";
        delete_option( $this->transient_name );

        //echo '<pre>';
        //print_r($_POST);
        //print_r($matches);
        //print_r($postarr);
        //print_r($data);
        //exit;

        $pattern = '#<a href="'.home_url().'[^>]+">.+?<\/a>#';
        $options = get_option('rms_inner_reference_options');
        if ( $data['post_type'] == 'post' || $data['post_type'] == 'page' ) {
            if ( preg_match_all($pattern, stripslashes($postarr['post_content']), $matches) < $options['rms_inner_reference_count'] ) {
                update_option( $this->transient_name, true );
                //if save or update post/page change post_status to draft
                if ( isset( $postarr[ 'publish' ] ) || isset( $postarr[ 'save' ] ) )
                    $data[ 'post_status' ] = 'draft';
            }
        }


        return $data;
    }


    /**
     * Hook check available error for current post and user and print error message in submit box.
     */
    public function rms_submitbox_start_handler() {
        if( get_option($this->transient_name) ) {
            delete_option($this->transient_name);
            echo '<p style="color:#dc3232;">10 internal links are required to publish this page - Add meaningful and useful links to other pages on this website to publish the page</p>';
        }

    }

}