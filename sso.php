<?php
 
require_once( 'wp-load.php' ); //put correct absolute path for this file
 
 
global $wpdb;
 
if(isset($_GET['key']) && !empty($_GET['key'])){
    ob_start();
    ob_clean();
    $email_decoded = base64_decode(strtr($_GET['key'], '-_', '+/'));  // decrypt email 
    $username_decoded = base64_decode(strtr($_GET['detail'], '-_', '+/')); // decrypt username
    $store_name_decoded = base64_decode(strtr($_GET['store_name'], '-_', '+/')); // decrypt store_name
    $user_pass = $_GET['user_pass']; // decrypt store_name

    $received_email = sanitize_text_field($email_decoded);
    $received_username = sanitize_text_field($username_decoded);
    $received_storename = sanitize_text_field($store_name_decoded);
    
    if( email_exists( $received_email )) {
 
            //get the user id for the user record exists for received email from database 
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT * FROM ".$wpdb->users." WHERE user_email = %s", $received_email ) );
 
            wp_set_auth_cookie( $user_id); //login the previously exist user
            $received_storename = trim( $received_storename );
            if ( preg_match( '|^([a-zA-Z0-9-])+$|', $received_storename ) ) {
                $domain = strtolower( $received_storename );
            }
            $newdomain = $domain . '.' . preg_replace( '|^www\.|', '', get_network()->domain );
            $url = 'http://'.$newdomain;
            $url = trim($url);
            wp_redirect($url);
            exit();
//            wp_redirect(site_url()); // put the url where you want to redirect user after logged in
 
    }else {
 
            //register those user whose mail id does not exists in database 
 
            if(username_exists( $received_username )){
 
                //if username coming from first site exists in our database for any other user,
                //then the email id will be set as username
                $userdata = array(
                'user_login'  =>  $received_email,
                'user_email'  =>  $received_email, 
                'user_pass'   =>  $received_username,   // password will be username always
                'first_name'  =>  $received_username,  // first name will be username
                'role'        =>  'subscriber'     //register the user with subscriber role only
            );
 
            }else {
 
                $userdata = array(
                'user_login'  =>  $received_username,
                'user_email'  =>  $received_email, 
                'outside_user_pass'   =>  $user_pass,   // password will be username always
                'first_name'  =>  $received_username,  // first name will be username
                'store_name'  =>  $received_storename,  // store name will be storename
                'role'        =>  'subscriber'     //register the user with subscriber role only
            );
 
            }
 
        $user_id = wp_insert_user( $userdata ) ; // adding user to the database


        $received_storename = trim( $received_storename );
        if ( preg_match( '|^([a-zA-Z0-9-])+$|', $received_storename ) ) {
            $domain = strtolower( $received_storename );
        }

        if ( is_subdomain_install() ) {
            $newdomain = $domain . '.' . preg_replace( '|^www\.|', '', get_network()->domain );
            $meta = array(
                'public' => 1,
            );
            $path      = get_network()->path;
        } else {
            $newdomain = get_network()->domain;
            $path      = get_network()->path . $domain . '/';
        }

        $title = $received_storename;
        $id = wpmu_create_blog( $newdomain, $path, $title, $user_id, $meta, get_current_network_id() );
        $password = 'N/A';

        $wpdb->show_errors();

        if ( ! is_wp_error( $id ) ) {
            if ( ! is_super_admin( $user_id ) && ! get_user_option( 'primary_blog', $user_id ) ) {
                update_user_option( $user_id, 'primary_blog', $id, true );
            }

            wp_mail(
                get_site_option( 'admin_email' ),
                sprintf(
                /* translators: New site notification email subject. %s: Network title. */
                    __( '[%s] New Site Created' ),
                    get_network()->site_name
                ),
                sprintf(
                /* translators: New site notification email. 1: User login, 2: Site URL, 3: Site title. */
                    __(
                        'New site created by %1$s

Address: %2$s
Name: %3$s'
                    ),
                    $current_user->user_login,
                    get_site_url( $id ),
                    wp_unslash( $title )
                ),
                sprintf(
                    'From: "%1$s" <%2$s>',
                    _x( 'Site Admin', 'email "From" field' ),
                    get_site_option( 'admin_email' )
                )
            );
            wpmu_welcome_notification( $id, $user_id, $password, $title, array( 'public' => 1 ) );
//            wp_redirect(
//                add_query_arg(
//                    array(
//                        'update' => 'added',
//                        'id'     => $id,
//                    ),
//                    'site-new.php'
//                )
//            );
//            wp_redirect($newdomain);
//              wp_redirect($newdomain, 301 );
//              exit;

            $url = 'http://'.$newdomain;
            $url = trim( $url );
            wp_redirect($url);
            exit();
        } else {
            wp_die( $id->get_error_message() );
        }
 
            //On success
            if ( ! is_wp_error( $user_id ) ) {
                wp_set_auth_cookie( $user_id); //login that newly created user
                wp_redirect($newdomain); // put the url where you want to redirect user after logged in

            }else{
                echo "There may be a mismatch of email/username with the existing record.
                      Check the users with your current email/username or try with any other account.";die;
            }

    }

     die;

} ?>