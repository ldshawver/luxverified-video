<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Marketing {

    const OPTION_KEY = 'luxvv_marketing';

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function register(): void {
        if ( false === get_option( self::OPTION_KEY, false ) ) {
            add_option( self::OPTION_KEY, self::defaults(), '', false );
        }

        register_setting(
            'luxvv_marketing_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize' ],
                'default'           => self::defaults(),
            ]
        );
    }

    public static function defaults(): array {
        return [
            'gsc_property_url' => '',
            'ga_property_id' => '',
            'data_studio_url' => '',
            'bigquery_project' => '',
            'competitors' => '',
            'target_locations' => '',
            'target_products' => '',
            'brand_tone' => 'Luxury, authoritative, adult-boutique.',
            'ai_workflow_notes' => '',
            'tool_status' => [],
        ];
    }

    public static function sanitize( $in ): array {
        $in = is_array( $in ) ? $in : [];
        $out = self::defaults();

        $out['gsc_property_url'] = isset( $in['gsc_property_url'] )
            ? esc_url_raw( $in['gsc_property_url'] )
            : $out['gsc_property_url'];
        $out['ga_property_id'] = isset( $in['ga_property_id'] )
            ? sanitize_text_field( $in['ga_property_id'] )
            : $out['ga_property_id'];
        $out['data_studio_url'] = isset( $in['data_studio_url'] )
            ? esc_url_raw( $in['data_studio_url'] )
            : $out['data_studio_url'];
        $out['bigquery_project'] = isset( $in['bigquery_project'] )
            ? sanitize_text_field( $in['bigquery_project'] )
            : $out['bigquery_project'];
        $out['competitors'] = isset( $in['competitors'] )
            ? sanitize_textarea_field( $in['competitors'] )
            : $out['competitors'];
        $out['target_locations'] = isset( $in['target_locations'] )
            ? sanitize_textarea_field( $in['target_locations'] )
            : $out['target_locations'];
        $out['target_products'] = isset( $in['target_products'] )
            ? sanitize_textarea_field( $in['target_products'] )
            : $out['target_products'];
        $out['brand_tone'] = isset( $in['brand_tone'] )
            ? sanitize_text_field( $in['brand_tone'] )
            : $out['brand_tone'];
        $out['ai_workflow_notes'] = isset( $in['ai_workflow_notes'] )
            ? sanitize_textarea_field( $in['ai_workflow_notes'] )
            : $out['ai_workflow_notes'];

        $tool_status = isset( $in['tool_status'] ) && is_array( $in['tool_status'] )
            ? $in['tool_status']
            : [];
        $out['tool_status'] = [];

        $valid_tools = array_keys( self::tool_catalog() );
        foreach ( $valid_tools as $tool_key ) {
            if ( ! empty( $tool_status[ $tool_key ] ) ) {
                $out['tool_status'][ $tool_key ] = 1;
            }
        }

        return $out;
    }

    public static function all(): array {
        return get_option( self::OPTION_KEY, self::defaults() );
    }

    public static function tool_catalog(): array {
        return [
            'gsc' => [
                'label' => 'Google Search Console',
                'url' => 'https://search.google.com/search-console',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Real traffic, CTR, and position data.',
            ],
            'keyword_planner' => [
                'label' => 'Google Keyword Planner',
                'url' => 'https://ads.google.com/home/tools/keyword-planner/',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Volume + competition data (free with Ads account).',
            ],
            'google_trends' => [
                'label' => 'Google Trends',
                'url' => 'https://trends.google.com',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Seasonality + emerging topics.',
            ],
            'people_also_ask' => [
                'label' => 'People Also Ask / Related Searches',
                'url' => 'https://www.google.com',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Manual SERP research.',
            ],
            'ubersuggest' => [
                'label' => 'Ubersuggest (Free tier)',
                'url' => 'https://neilpatel.com/ubersuggest/',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Limited keyword + CPC data.',
            ],
            'answer_the_public' => [
                'label' => 'AnswerThePublic',
                'url' => 'https://answerthepublic.com',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Question-based keyword ideas.',
            ],
            'keyword_surfer' => [
                'label' => 'Keyword Surfer (Chrome)',
                'url' => 'https://surferseo.com/keyword-surfer-extension/',
                'category' => 'Keyword & SEO Insights',
                'note' => 'On-SERP volume + CPC.',
            ],
            'soovle' => [
                'label' => 'Soovle',
                'url' => 'https://soovle.com',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Cross-platform suggestions.',
            ],
            'keyworddit' => [
                'label' => 'Keyworddit',
                'url' => 'https://keyworddit.com',
                'category' => 'Keyword & SEO Insights',
                'note' => 'Subreddit keyword mining.',
            ],
            'similarweb' => [
                'label' => 'SimilarWeb (Free)',
                'url' => 'https://www.similarweb.com',
                'category' => 'Traffic & Competitor Insights',
                'note' => 'Top traffic sources + referrals.',
            ],
            'meta_ad_library' => [
                'label' => 'Meta Ad Library',
                'url' => 'https://www.facebook.com/ads/library/',
                'category' => 'Traffic & Competitor Insights',
                'note' => 'Competitor ads on Facebook/Instagram.',
            ],
            'tiktok_creative_center' => [
                'label' => 'TikTok Creative Center',
                'url' => 'https://ads.tiktok.com/business/creativecenter/',
                'category' => 'Traffic & Competitor Insights',
                'note' => 'TikTok ad insights.',
            ],
            'pinterest_trends' => [
                'label' => 'Pinterest Trends',
                'url' => 'https://trends.pinterest.com',
                'category' => 'Traffic & Competitor Insights',
                'note' => 'Lifestyle trend signals.',
            ],
            'chatgpt' => [
                'label' => 'ChatGPT',
                'url' => 'https://chat.openai.com',
                'category' => 'AI Creative & Insight',
                'note' => 'Main hub for clustering, copy, and summaries.',
            ],
            'bing_chat' => [
                'label' => 'Bing Chat',
                'url' => 'https://www.bing.com/chat',
                'category' => 'AI Creative & Insight',
                'note' => 'Query expansion and SERP synthesis.',
            ],
            'perplexity' => [
                'label' => 'Perplexity.ai',
                'url' => 'https://www.perplexity.ai',
                'category' => 'AI Creative & Insight',
                'note' => 'Research summaries + citations.',
            ],
            'youtube_search' => [
                'label' => 'YouTube Search',
                'url' => 'https://www.youtube.com',
                'category' => 'AI Creative & Insight',
                'note' => 'Video keyword research.',
            ],
            'ga_bigquery' => [
                'label' => 'Google Analytics + BigQuery',
                'url' => 'https://analytics.google.com',
                'category' => 'Reporting & Analytics',
                'note' => 'Export GA data for AI clustering.',
            ],
            'data_studio' => [
                'label' => 'Looker Studio (Data Studio)',
                'url' => 'https://lookerstudio.google.com',
                'category' => 'Reporting & Analytics',
                'note' => 'Dashboards for performance reporting.',
            ],
            'canva' => [
                'label' => 'Canva',
                'url' => 'https://www.canva.com',
                'category' => 'Creative & Campaign Tools',
                'note' => 'Design templates + video assets.',
            ],
            'capcut' => [
                'label' => 'CapCut',
                'url' => 'https://www.capcut.com',
                'category' => 'Creative & Campaign Tools',
                'note' => 'Video editing for social.',
            ],
            'mailerlite' => [
                'label' => 'MailerLite',
                'url' => 'https://www.mailerlite.com',
                'category' => 'Creative & Campaign Tools',
                'note' => 'Email capture + campaigns.',
            ],
            'mailchimp' => [
                'label' => 'Mailchimp',
                'url' => 'https://mailchimp.com',
                'category' => 'Creative & Campaign Tools',
                'note' => 'Email automation.',
            ],
            'buffer' => [
                'label' => 'Buffer',
                'url' => 'https://buffer.com',
                'category' => 'Creative & Campaign Tools',
                'note' => 'Social scheduling.',
            ],
            'hootsuite' => [
                'label' => 'Hootsuite',
                'url' => 'https://www.hootsuite.com',
                'category' => 'Creative & Campaign Tools',
                'note' => 'Social scheduling.',
            ],
            'bitly' => [
                'label' => 'Bitly',
                'url' => 'https://bitly.com',
                'category' => 'Reporting & Analytics',
                'note' => 'Link tracking + CTRs.',
            ],
        ];
    }

    public static function workflow_templates(): array {
        return [
            'seed_prompt' => "Cluster these keywords into buyer intent stages (Informational, Commercial, Transactional, Retention). Return clusters with 5-10 keyword variations each.",
            'competitor_prompt' => "Summarize competitor pages and ads. Identify 3 gaps and 3 winning angles we can own.",
            'copy_prompt' => "Create 3 ad variants for [audience] using [keywords] in a luxury, adult-boutique tone. Include headline, primary text, and CTA.",
            'optimization_prompt' => "Given pages with impressions + avg position, suggest 5 quick optimization actions per page.",
        ];
    }

    public static function weekly_workflow(): array {
        return [
            'Gather 150-200 seed keywords from free tools (Trends, Soovle, AnswerThePublic).',
            'Cluster keywords by intent using AI prompts.',
            'Review competitor ads + content for gaps and angles.',
            'Generate campaign copy + landing page messaging.',
            'Export GSC + GA data for AI optimization suggestions.',
            'Update Looker Studio dashboard and summarize results.',
        ];
    }

    public static function export_config(): array {
        return [
            'settings' => self::all(),
            'tools' => self::tool_catalog(),
            'workflow' => self::weekly_workflow(),
            'prompts' => self::workflow_templates(),
        ];
    }
}
