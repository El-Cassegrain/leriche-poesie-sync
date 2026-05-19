<?php
/**
 * Classe Leriche_FTP_Uploader
 * Gere l'upload des fichiers generes vers leriche-poesie.com via FTP/FTPS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Leriche_FTP_Uploader {

    private $options;
    private $conn;

    public function __construct( array $options ) {
        $this->options = $options;
    }

    /**
     * Upload une liste de fichiers locaux vers le serveur FTP.
     *
     * @param array $files [ 'chemin/local/fichier.html' => 'chemin/distant/fichier.html' ]
     * @return bool true si tout s'est bien passe
     */
    public function upload( array $files ): bool {
        if ( ! $this->connect() ) {
            leriche_sync_log( 'FTP: connexion impossible a ' . $this->options['ftp_host'], 'error' );
            return false;
        }

        $success = true;
        foreach ( $files as $local => $remote ) {
            if ( ! file_exists( $local ) ) {
                leriche_sync_log( "FTP: fichier local introuvable: $local", 'warning' );
                continue;
            }
            $this->ensure_remote_dir( dirname( $remote ) );
            if ( ! ftp_put( $this->conn, $remote, $local, FTP_BINARY ) ) {
                leriche_sync_log( "FTP: echec upload $local -> $remote", 'error' );
                $success = false;
            } else {
                leriche_sync_log( "FTP: envoye $remote" );
            }
        }

        ftp_close( $this->conn );
        return $success;
    }

    /**
     * Etablit la connexion FTP (avec support SSL optionnel).
     */
    private function connect(): bool {
        $host = $this->options['ftp_host'];
        $port = isset( $this->options['ftp_port'] ) ? (int) $this->options['ftp_port'] : 21;
        $ssl  = ! empty( $this->options['ftp_ssl'] );

        if ( $ssl && function_exists( 'ftp_ssl_connect' ) ) {
            $this->conn = ftp_ssl_connect( $host, $port, 30 );
        } else {
            $this->conn = ftp_connect( $host, $port, 30 );
        }

        if ( ! $this->conn ) return false;

        if ( ! ftp_login( $this->conn, $this->options['ftp_user'], $this->options['ftp_pass'] ) ) {
            ftp_close( $this->conn );
            return false;
        }

        ftp_pasv( $this->conn, true ); // mode passif recommande

        // Changer vers le dossier racine distant
        if ( ! empty( $this->options['ftp_remote_dir'] ) ) {
            ftp_chdir( $this->conn, $this->options['ftp_remote_dir'] );
        }

        return true;
    }

    /**
     * Cree recursivement les dossiers distants si necessaire.
     */
    private function ensure_remote_dir( string $dir ): void {
        if ( $dir === '.' || $dir === '/' || empty( $dir ) ) return;
        $this->ensure_remote_dir( dirname( $dir ) );
        // Tente de changer de dossier, sinon le cree
        $current = ftp_pwd( $this->conn );
        if ( ! @ftp_chdir( $this->conn, $dir ) ) {
            @ftp_mkdir( $this->conn, $dir );
        }
        ftp_chdir( $this->conn, $current );
    }
}