<?php
/**
 * Plugin Name: Leriche Poesie Sync
 * Plugin URI:  https://github.com/El-Cassegrain/leriche-poesie-sync
 * Description: Detecte les modifications de contenu WordPress et declenche la generation + upload FTP vers leriche-poesie.com
 * Version:     1.0.0
 * Author:      Etienne Leriche
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LERICHE_SYNC_VERSION', '1.0.0' );
define( 'LERICHE_SYNC_DIR', plugin_dir_path( __FILE__ ) );

require_once LERICHE_SYNC_DIR . 'includes/class-ftp-uploader.php';
require_once LERICHE_SYNC_DIR . 'includes/class-static-generator.php';
require_once LERICHE_SYNC_DIR . 'admin/settings-page.php';

add_action( 'save_post', 'leriche_sync_on_save', 10, 3 );
function leriche_sync_on_save( $post_id, $post, $update ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! in_array( $post->post_status, [ 'publish' ] ) ) return;
    leriche_sync_trigger( $post_id );
}

add_action( 'transition_post_status', 'leriche_sync_on_status_change', 10, 3 );
function leriche_sync_on_status_change( $new_status, $old_status, $post ) {
    if ( $new_status === 'publish' && $old_status !== 'publish' ) {
        leriche_sync_trigger( $post->ID );
    }
}

add_action( 'before_delete_post', 'leriche_sync_on_delete' );
function leriche_sync_on_delete( $post_id ) {
    leriche_sync_trigger( $post_id );
}

function leriche_sync_trigger( $post_id ) {
    $options = get_option( 'leriche_sync_options', [] );
    if ( empty( $options['ftp_host'] ) ) {
        leriche_sync_log( 'FTP non configure. Sync annule.' );
        return;
    }
    $generator = new Leriche_Static_Generator( $options );
    $generated_files = $generator->generate();
    if ( empty( $generated_files ) ) {
        leriche_sync_log( 'Aucun fichier genere.' );
        return;
    }
    $uploader = new Leriche_FTP_Uploader( $options );
    $result   = $uploader->upload( $generated_files );
    if ( $result ) {
        leriche_sync_log( 'Sync reussi. ' . count( $generated_files ) . ' fichier(s) envoye(s).' );
        update_option( 'leriche_sync_last_sync', current_time( 'mysql' ) );
    } else {
        leriche_sync_log( 'Erreur lors du sync FTP.', 'error' );
    }
}

function leriche_sync_log( $message, $level = 'info' ) {
    $log   = get_option( 'leriche_sync_log', [] );
    $log[] = [ 'time' => current_time( 'mysql' ), 'level' => $level, 'message' => $message ];
    if ( count( $log ) > 100 ) { $log = array_slice( $log, -100 ); }
    update_option( 'leriche_sync_log', $log );
}

add_action( 'admin_bar_menu', 'leriche_sync_admin_bar_button', 100 );
function leriche_sync_admin_bar_button( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $wp_admin_bar->add_node( [
        'id'    => 'leriche-sync-manual',
        'title' => '[sync] Sync FTP',
        'href'  => wp_nonce_url( admin_url( 'admin.php?page=leriche-sync&action=manual_sync' ), 'leriche_manual_sync' ),
    ] );
}

add_action( 'admin_init', 'leriche_sync_handle_manual' );
function leriche_sync_handle_manual() {
    if ( ! isset( $_GET['page'], $_GET['action'] ) ) return;
    if ( $_GET['page'] !== 'leriche-sync' || $_GET['action'] !== 'manual_sync' ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'leriche_manual_sync' );
    leriche_sync_trigger( 0 );
    wp_safe_redirect( admin_url( 'admin.php?page=leriche-sync&synced=1' ) );
    exit;
}