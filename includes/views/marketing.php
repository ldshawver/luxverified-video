<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$opt_key = class_exists( '\\LuxVerified\\Marketing' ) ? Marketing::OPTION_KEY : 'luxvv_marketing';
$tool_status = $settings['tool_status'] ?? [];
?>
<div class="wrap luxvv-marketing">
    <h1>LUX Marketing</h1>
    <p class="description">Use free tools + AI workflows to build ads strategy, reports, competitor insights, and keyword plans inside LUX Marketing.</p>

    <form method="post" action="options.php">
        <?php settings_fields( 'luxvv_marketing_group' ); ?>

        <div class="luxvv-card">
            <h2>Accounts &amp; Data Sources</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="luxvv-gsc-property">Google Search Console Property</label></th>
                    <td>
                        <input type="url" id="luxvv-gsc-property" class="regular-text" name="<?php echo esc_attr( $opt_key ); ?>[gsc_property_url]" value="<?php echo esc_attr( $settings['gsc_property_url'] ?? '' ); ?>" placeholder="https://yourdomain.com/" />
                        <p class="description">Add the verified GSC property URL to anchor AI reporting.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-ga-property">Google Analytics Property ID</label></th>
                    <td>
                        <input type="text" id="luxvv-ga-property" class="regular-text" name="<?php echo esc_attr( $opt_key ); ?>[ga_property_id]" value="<?php echo esc_attr( $settings['ga_property_id'] ?? '' ); ?>" placeholder="G-XXXXXXXX" />
                        <p class="description">Used for GA/BigQuery exports and monthly reporting.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-data-studio">Looker Studio Dashboard</label></th>
                    <td>
                        <input type="url" id="luxvv-data-studio" class="regular-text" name="<?php echo esc_attr( $opt_key ); ?>[data_studio_url]" value="<?php echo esc_attr( $settings['data_studio_url'] ?? '' ); ?>" placeholder="https://lookerstudio.google.com/" />
                        <p class="description">Optional dashboard link for quick access.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-bigquery-project">BigQuery Project</label></th>
                    <td>
                        <input type="text" id="luxvv-bigquery-project" class="regular-text" name="<?php echo esc_attr( $opt_key ); ?>[bigquery_project]" value="<?php echo esc_attr( $settings['bigquery_project'] ?? '' ); ?>" placeholder="project-id" />
                        <p class="description">Used for GA export pipelines.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-competitors">Competitors</label></th>
                    <td>
                        <textarea id="luxvv-competitors" class="large-text" rows="3" name="<?php echo esc_attr( $opt_key ); ?>[competitors]" placeholder="competitor.com&#10;competitor2.com"><?php echo esc_textarea( $settings['competitors'] ?? '' ); ?></textarea>
                        <p class="description">One domain per line. Used for manual audit notes and AI prompts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-target-products">Target Products / Services</label></th>
                    <td>
                        <textarea id="luxvv-target-products" class="large-text" rows="3" name="<?php echo esc_attr( $opt_key ); ?>[target_products]" placeholder="Signature bundles&#10;Memberships"><?php echo esc_textarea( $settings['target_products'] ?? '' ); ?></textarea>
                        <p class="description">Used to tailor AI clustering and copy prompts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-target-locations">Target Locations</label></th>
                    <td>
                        <textarea id="luxvv-target-locations" class="large-text" rows="3" name="<?php echo esc_attr( $opt_key ); ?>[target_locations]" placeholder="New York, Miami, Los Angeles"><?php echo esc_textarea( $settings['target_locations'] ?? '' ); ?></textarea>
                        <p class="description">Used for geo-specific ad strategy prompts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="luxvv-brand-tone">Brand Tone</label></th>
                    <td>
                        <input type="text" id="luxvv-brand-tone" class="regular-text" name="<?php echo esc_attr( $opt_key ); ?>[brand_tone]" value="<?php echo esc_attr( $settings['brand_tone'] ?? '' ); ?>" />
                        <p class="description">Example: Luxury, authoritative, adult-boutique.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="luxvv-card">
            <h2>Free Tools Checklist</h2>
            <p class="description">Mark which tools are already configured or active for LUX Marketing.</p>
            <?php foreach ( $grouped_tools as $category => $category_tools ) : ?>
                <h3><?php echo esc_html( $category ); ?></h3>
                <div class="luxvv-tool-grid">
                    <?php foreach ( $category_tools as $tool_key => $tool ) : ?>
                        <label class="luxvv-tool">
                            <input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[tool_status][<?php echo esc_attr( $tool_key ); ?>]" value="1" <?php checked( ! empty( $tool_status[ $tool_key ] ) ); ?> />
                            <span class="luxvv-tool-title"><?php echo esc_html( $tool['label'] ); ?></span>
                            <span class="luxvv-tool-note"><?php echo esc_html( $tool['note'] ); ?></span>
                            <a class="luxvv-tool-link" href="<?php echo esc_url( $tool['url'] ); ?>" target="_blank" rel="noopener">Open tool</a>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="luxvv-card">
            <h2>AI Workflow Prompts</h2>
            <p class="description">Copy/paste these prompts into LUX AI or external agents.</p>
            <div class="luxvv-prompt-grid">
                <?php foreach ( $prompts as $prompt_key => $prompt_text ) : ?>
                    <div class="luxvv-prompt">
                        <h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $prompt_key ) ) ); ?></h3>
                        <textarea class="large-text" rows="3" readonly><?php echo esc_textarea( $prompt_text ); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="luxvv-card">
            <h2>Weekly Workflow</h2>
            <ol class="luxvv-workflow">
                <?php foreach ( $workflow as $step ) : ?>
                    <li><?php echo esc_html( $step ); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="luxvv-card">
            <h2>AI Workflow Notes</h2>
            <textarea class="large-text" rows="4" name="<?php echo esc_attr( $opt_key ); ?>[ai_workflow_notes]" placeholder="Add any custom AI agent workflow notes here."><?php echo esc_textarea( $settings['ai_workflow_notes'] ?? '' ); ?></textarea>
        </div>

        <?php submit_button( 'Save LUX Marketing Settings' ); ?>
    </form>
</div>
