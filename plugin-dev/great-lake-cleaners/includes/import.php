<?php
/**
 * One-time CSV importer.
 * Tools → Import Cleanups CSV
 * Accepts the cleanups.csv format from the Python documentation system.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function() {
    add_management_page(
        'Import Cleanups CSV',
        'Import Cleanups CSV',
        'manage_options',
        'grs-import',
        'glc_import_page'
    );
} );

function glc_import_page() {
    $results = [];
    $error   = '';

    if ( isset( $_POST['glc_import_nonce'] ) &&
         wp_verify_nonce( $_POST['glc_import_nonce'], 'glc_import' ) &&
         ! empty( $_FILES['csv_file']['tmp_name'] ) ) {

        $results = glc_process_csv_import( $_FILES['csv_file']['tmp_name'] );
    }
    ?>
    <div class="wrap">
        <h1>Import Cleanups CSV</h1>
        <p>Upload your <code>cleanups.csv</code> file to seed the database.
           Duplicate dates + site names are skipped automatically.</p>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'glc_import', 'glc_import_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="csv_file">CSV File</label></th>
                    <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                </tr>
            </table>
            <?php submit_button( 'Import' ); ?>
        </form>

        <?php if ( ! empty( $results ) ) : ?>
        <h2>Import Results</h2>
        <table class="widefat striped">
            <thead><tr><th>Date</th><th>Site</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ( $results as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r['date'] ); ?></td>
                    <td><?php echo esc_html( $r['site'] ); ?></td>
                    <td><?php echo esc_html( $r['status'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function glc_process_csv_import( $filepath ) {
    $results = [];
    $handle  = fopen( $filepath, 'r' );
    if ( ! $handle ) return [ ['date'=>'','site'=>'','status'=>'Could not open file.'] ];

    $headers = array_map( 'trim', fgetcsv( $handle ) );

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        if ( count( $row ) < count( $headers ) ) continue;
        $data = array_combine( $headers, $row );

        $date = trim( $data['date'] ?? '' );
        $site = trim( $data['site_name'] ?? '' );
        if ( ! $date || ! $site ) continue;

        // Skip duplicates
        $existing = get_posts( [
            'post_type'  => 'cleanup_event',
            'meta_query' => [
                [ 'key' => 'cleanup_date', 'value' => $date ],
                [ 'key' => 'site_name',    'value' => $site ],
            ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );
        if ( ! empty( $existing ) ) {
            $results[] = [ 'date' => $date, 'site' => $site, 'status' => 'Skipped (duplicate)' ];
            continue;
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'cleanup_event',
            'post_status' => 'publish',
            'post_title'  => $site . ' — ' . $date,
        ] );

        if ( is_wp_error( $post_id ) ) {
            $results[] = [ 'date' => $date, 'site' => $site, 'status' => 'Error: ' . $post_id->get_error_message() ];
            continue;
        }

        $field_map = [
            'cleanup_date'       => 'date',
            'site_name'          => 'site_name',
            'corridor'           => 'corridor',
            'gps_lat'            => 'gps_lat',
            'gps_lon'            => 'gps_lon',
            'volunteers'         => 'volunteers',
            'hours'              => 'hours',
            'bags'               => 'bags',
            'weight_kg'          => 'weight_kg',
            'items_recycled'     => 'items_recycled',
            'tires_removed'      => 'tires_removed',
            'recycled_weight_kg' => 'recycled_weight_kg',
            'hazards_removed'    => 'hazards_removed',
            'species_planted'    => 'species_planted',
            'meters_bank_cleared'=> 'meters_bank_cleared',
            'notable_finds'      => 'notable_finds',
            'wildlife_obs'       => 'wildlife_obs',
            'instagram_url'      => 'instagram_url',
        ];

        // ACF field key map — lets ACF recognise values saved by the importer
        $acf_key_map = [
            'cleanup_date'        => 'field_glc_date',
            'site_name'           => 'field_glc_site',
            'gps_lat'             => 'field_glc_lat',
            'gps_lon'             => 'field_glc_lon',
            'volunteers'          => 'field_glc_volunteers',
            'hours'               => 'field_glc_hours',
            'bags'                => 'field_glc_bags',
            'weight_kg'           => 'field_glc_weight',
            'items_recycled'      => 'field_glc_recycled',
            'tires_removed'       => 'field_glc_tires',
            'hazards_removed'     => 'field_glc_hazards',
            'notable_finds'       => 'field_glc_notable',
            'species_planted'     => 'field_glc_planted',
            'meters_bank_cleared' => 'field_glc_bank',
            'wildlife_obs'        => 'field_glc_wildlife',
            'instagram_url'       => 'field_glc_instagram',
        ];

        foreach ( $field_map as $meta_key => $csv_col ) {
            $val = trim( $data[ $csv_col ] ?? '' );
            if ( $val !== '' ) {
                update_post_meta( $post_id, $meta_key, $val );
                // Write ACF pointer so the field renders correctly in the editor
                if ( isset( $acf_key_map[ $meta_key ] ) ) {
                    update_post_meta( $post_id, '_' . $meta_key, $acf_key_map[ $meta_key ] );
                }
            }
        }

        // Instagram URL — sanitize as URL
        if ( ! empty( $data['instagram_url'] ) ) {
            $url = esc_url_raw( trim( $data['instagram_url'] ) );
            if ( $url ) {
                update_post_meta( $post_id, 'instagram_url', $url );
                update_post_meta( $post_id, '_instagram_url', 'field_glc_instagram' );
            }
        }

        // Post content from notes field
        if ( ! empty( $data['notes'] ) ) {
            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => sanitize_textarea_field( $data['notes'] ),
            ] );
        }

        $results[] = [ 'date' => $date, 'site' => $site, 'status' => 'Imported ✓' ];
    }

    fclose( $handle );
    return $results;
}
