<?php
/**
 * Page de configuration du plugin Leriche Sync dans l'admin WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Enregistrer le menu et la page
add_action( 'admin_menu', 'leriche_sync_admin_menu' );
function leriche_sync_admin_menu() {
    add_menu_page(
        'Leriche Sync',
        'Leriche Sync',
        'manage_options',
        'leriche-sync',
        'leriche_sync_settings_page',
        'dashicons-upload',
        80
    );
}

// Enregistrer les options
add_action( 'admin_init', 'leriche_sync_register_settings' );
function leriche_sync_register_settings() {
    register_setting( 'leriche_sync_options_group', 'leriche_sync_options', 'leriche_sync_sanitize_options' );

    add_settings_section( 'leriche_sync_ftp', 'Connexion FTP', '__return_false', 'leriche-sync' );

    $fields = [
        'ftp_host'       => 'Hote FTP (ex: ftp.leriche-poesie.com)',
        'ftp_port'       => 'Port FTP (defaut: 21)',
        'ftp_user'       => 'Utilisateur FTP',
        'ftp_pass'       => 'Mot de passe FTP',
        'ftp_remote_dir' => 'Dossier distant (ex: /public_html)',
        'ftp_ssl'        => 'Connexion FTPS (SSL)',
        'copy_assets'    => 'Copier les assets du theme (CSS/JS/images)',
    ];

    foreach ( $fields as $key => $label ) {
        add_settings_field(
            'leriche_sync_' . $key,
            $label,
            'leriche_sync_field_' . $key,
            'leriche-sync',
            'leriche_sync_ftp'
        );
    }
}

function leriche_sync_sanitize_options( $input ) {
    $clean = [];
    $text_fields = [ 'ftp_host', 'ftp_port', 'ftp_user', 'ftp_remote_dir' ];
    foreach ( $text_fields as $f ) {
        $clean[ $f ] = sanitize_text_field( $input[ $f ] ?? '' );
    }
    $clean['ftp_pass']     = $input['ftp_pass'] ?? '';  // pas de sanitize pour le mot de passe
    $clean['ftp_ssl']      = ! empty( $input['ftp_ssl'] ) ? 1 : 0;
    $clean['copy_assets']  = ! empty( $input['copy_assets'] ) ? 1 : 0;
    return $clean;
}

// Callbacks des champs
function leriche_sync_field_ftp_host() { leriche_sync_text_field( 'ftp_host', 'ftp.leriche-poesie.com' ); }
function leriche_sync_field_ftp_port() { leriche_sync_text_field( 'ftp_port', '21' ); }
function leriche_sync_field_ftp_user() { leriche_sync_text_field( 'ftp_user', '' ); }
function leriche_sync_field_ftp_remote_dir() { leriche_sync_text_field( 'ftp_remote_dir', '/public_html' ); }
function leriche_sync_field_ftp_pass() {
    $opts = get_option( 'leriche_sync_options', [] );
    $val  = $opts['ftp_pass'] ?? '';
    echo '<input type="password" name="leriche_sync_options[ftp_pass]" value="' . esc_attr( $val ) . '" class="regular-text" autocomplete="new-password">';
}
function leriche_sync_field_ftp_ssl() {
    $opts = get_option( 'leriche_sync_options', [] );
    $val  = $opts['ftp_ssl'] ?? 0;
    echo '<input type="checkbox" name="leriche_sync_options[ftp_ssl]" value="1" ' . checked( 1, $val, false ) . '> Activer FTPS';
}
function leriche_sync_field_copy_assets() {
    $opts = get_option( 'leriche_sync_options', [] );
    $val  = $opts['copy_assets'] ?? 0;
    echo '<input type="checkbox" name="leriche_sync_options[copy_assets]" value="1" ' . checked( 1, $val, false ) . '> Inclure CSS/JS/images du theme';
}

function leriche_sync_text_field( $key, $placeholder = '' ) {
    $opts = get_option( 'leriche_sync_options', [] );
    $val  = $opts[ $key ] ?? '';
    echo '<input type="text" name="leriche_sync_options[' . $key . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text">';
}

/**
 * Rendu de la page de parametres.
 */
function leriche_sync_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $last_sync = get_option( 'leriche_sync_last_sync', null );
    $log       = get_option( 'leriche_sync_log', [] );
    $synced    = isset( $_GET['synced'] ) && $_GET['synced'] === '1';
    ?>
    <div class="wrap">
        <h1>Leriche Poesie Sync</h1>

        <?php if ( $synced ) : ?>
            <div class="notice notice-success is-dismissible"><p>Synchronisation manuelle effectuee avec succes.</p></div>
        <?php endif; ?>

        <div style="display:flex;gap:20px;align-items:center;margin-bottom:16px;">
            <div>
                <?php if ( $last_sync ) : ?>
                    <p><strong>Derniere sync :</strong> <?php echo esc_html( $last_sync ); ?></p>
                <?php else : ?>
                    <p>Aucune synchronisation effectuee pour l instant.</p>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=leriche-sync&action=manual_sync' ), 'leriche_manual_sync' ) ); ?>"
               class="button button-primary">
                Lancer une synchronisation manuelle
            </a>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'leriche_sync_options_group' );
            do_settings_sections( 'leriche-sync' );
            submit_button( 'Enregistrer les parametres FTP' );
            ?>
        </form>

        <h2>Journal des synchronisations (100 derniers)</h2>
        <?php if ( empty( $log ) ) : ?>
            <p>Aucun journal disponible.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead><tr><th>Date</th><th>Niveau</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ( array_reverse( $log ) as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( $entry['time'] ); ?></td>
                        <td><?php echo esc_html( $entry['level'] ); ?></td>
                        <td><?php echo esc_html( $entry['message'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}