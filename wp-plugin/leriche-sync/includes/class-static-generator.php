<?php
/**
 * Classe Leriche_Static_Generator
 * Recupere les pages/posts WordPress via WP_Query et les enregistre en HTML statique.
 * Utilise wp_remote_get() pour recuperer le rendu complet (avec le theme actif).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Leriche_Static_Generator {

    private $options;
    private $output_dir;

    public function __construct( array $options ) {
        $this->options   = $options;
        $this->output_dir = trailingslashit( get_temp_dir() ) . 'leriche-static/';
    }

    /**
     * Genere tous les fichiers HTML statiques.
     *
     * @return array [ chemin_local => chemin_distant ]
     */
    public function generate(): array {
        // Nettoyer le dossier temporaire
        $this->clean_output_dir();
        if ( ! wp_mkdir_p( $this->output_dir ) ) {
            leriche_sync_log( 'Impossible de creer le dossier temp: ' . $this->output_dir, 'error' );
            return [];
        }

        $files = [];

        // Generer la page d'accueil
        $files = array_merge( $files, $this->generate_url( home_url('/'), 'index.html' ) );

        // Generer tous les posts publies
        $query = new WP_Query( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ] );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $slug    = get_post_field( 'post_name', get_the_ID() );
                $type    = get_post_type();
                $url     = get_permalink();

                if ( $type === 'post' ) {
                    $remote = 'poemes/' . $slug . '/index.html';
                } elseif ( $type === 'page' ) {
                    $remote = $slug . '/index.html';
                } else {
                    $remote = $type . '/' . $slug . '/index.html';
                }

                $files = array_merge( $files, $this->generate_url( $url, $remote ) );
            }
            wp_reset_postdata();
        }

        // Copier les assets (CSS, JS, images) si configure
        if ( ! empty( $this->options['copy_assets'] ) ) {
            $files = array_merge( $files, $this->collect_assets() );
        }

        return $files;
    }

    /**
     * Recupere le HTML d'une URL et l'enregistre localement.
     *
     * @return array [ chemin_local => chemin_distant ]
     */
    private function generate_url( string $url, string $remote_path ): array {
        $response = wp_remote_get( $url, [
            'timeout'    => 30,
            'user-agent' => 'LericheSyncBot/1.0',
        ] );

        if ( is_wp_error( $response ) ) {
            leriche_sync_log( 'Erreur recuperation URL ' . $url . ': ' . $response->get_error_message(), 'error' );
            return [];
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) return [];

        // Réécriture des URLs absolues en relatives
        $html = $this->rewrite_urls( $html );

        $local_path = $this->output_dir . $remote_path;
        wp_mkdir_p( dirname( $local_path ) );

        if ( file_put_contents( $local_path, $html ) === false ) {
            leriche_sync_log( 'Impossible d ecrire: ' . $local_path, 'error' );
            return [];
        }

        return [ $local_path => $remote_path ];
    }

    /**
     * Reecrit les URLs du site source vers des chemins relatifs.
     */
    private function rewrite_urls( string $html ): string {
        $source_url = rtrim( get_site_url(), '/' );
        // Remplacer les URLs absolues par des chemins relatifs
        $html = str_replace( $source_url, '', $html );
        return $html;
    }

    /**
     * Collecte les assets statiques (CSS, JS, images) du theme.
     *
     * @return array
     */
    private function collect_assets(): array {
        $files      = [];
        $theme_dir  = get_template_directory();
        $theme_uri  = get_template_directory_uri();
        $source_url = get_site_url();

        // Parcourir le dossier du theme
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        $allowed_ext = [ 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'ico' ];

        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() ) continue;
            $ext = strtolower( $file->getExtension() );
            if ( ! in_array( $ext, $allowed_ext ) ) continue;

            $local_path  = $file->getRealPath();
            $rel_path    = 'wp-content/themes/' . basename( $theme_dir ) . '/' . ltrim( str_replace( $theme_dir, '', $local_path ), DIRECTORY_SEPARATOR );
            $remote_path = str_replace( DIRECTORY_SEPARATOR, '/', $rel_path );

            // Copier vers output_dir
            $dest = $this->output_dir . $remote_path;
            wp_mkdir_p( dirname( $dest ) );
            if ( @copy( $local_path, $dest ) ) {
                $files[ $dest ] = $remote_path;
            }
        }

        return $files;
    }

    /**
     * Supprime le contenu du dossier temporaire.
     */
    private function clean_output_dir(): void {
        if ( ! is_dir( $this->output_dir ) ) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->output_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $files as $f ) {
            $f->isDir() ? rmdir( $f->getRealPath() ) : unlink( $f->getRealPath() );
        }
    }
}