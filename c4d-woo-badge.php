<?php
/*
Plugin Name: C4D Woo Badge
Plugin URI: http://coffee4dev.com/
Description: Sale badge with percent or specify amount. Otherwise we provide News Badge, Popular Badge, Sold Badge 
Author: Coffee4dev.com
Author URI: http://coffee4dev.com/
Text Domain: c4d-woo-badge
Version: 2.0.0
*/

define('C4DWBADGE_PLUGIN_URI', plugins_url('', __FILE__));

add_action( 'wp_enqueue_scripts', 'c4d_woo_badge_safely_add_stylesheet_to_frontsite');
add_filter('woocommerce_sale_flash', 'c4d_woo_sale_flash', 10, 3);
add_shortcode('c4d-woo-badge-sale', 'c4d_woo_badge_shortcode_sale');
add_shortcode('c4d-woo-badge-news', 'c4d_woo_badge_shortcode_news');
add_shortcode('c4d-woo-badge-popular', 'c4d_woo_badge_shortcode_popular');
add_shortcode('c4d-woo-badge-featured', 'c4d_woo_badge_shortcode_featured');
add_action('c4d-plugin-manager-section', 'c4d_woo_badge_section_options');
add_filter( 'plugin_row_meta', 'c4d_woo_badge_plugin_row_meta', 10, 2 );

function c4d_woo_badge_plugin_row_meta( $links, $file ) {
    if ( strpos( $file, basename(__FILE__) ) !== false ) {
        $new_links = array(
            'visit' => '<a href="http://coffee4dev.com">Visit Plugin Site</<a>',
            'forum' => '<a href="http://coffee4dev.com/forums/">Forum</<a>',
            'premium' => '<a href="http://coffee4dev.com">Premium Support</<a>'
        );
        
        $links = array_merge( $links, $new_links );
    }
    
    return $links;
}

function c4d_woo_badge_safely_add_stylesheet_to_frontsite( $page ) {
    if(!defined('C4DPLUGINMANAGER')) {
    	wp_enqueue_style( 'c4d-woo-badge-frontsite-style', C4DWBADGE_PLUGIN_URI.'/assets/default.css' );
    	wp_enqueue_script( 'c4d-woo-badge-frontsite-plugin-js', C4DWBADGE_PLUGIN_URI.'/assets/default.js', array( 'jquery' ), false, true ); 
    }
	wp_localize_script( 'jquery', 'c4d_woo_badge',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}

function c4d_woo_sale_flash($content, $post, $product){
	global $c4d_plugin_manager;
	if (isset($c4d_plugin_manager['woo-badge-auto-filter-action-onsale']) && $c4d_plugin_manager['woo-badge-auto-filter-action-onsale']) {
		$content = c4d_woo_badge_sale($product, array());	
	}
	if (isset($c4d_plugin_manager['woo-badge-auto-filter-action-new']) && $c4d_plugin_manager['woo-badge-auto-filter-action-new']) {
		$content .= do_shortcode('[c4d-woo-badge-news]');	
	}
	if (isset($c4d_plugin_manager['woo-badge-auto-filter-action-popular']) && $c4d_plugin_manager['woo-badge-auto-filter-action-popular']) {
		$content .= do_shortcode('[c4d-woo-badge-popular]');	
	}

	if (isset($c4d_plugin_manager['woo-badge-auto-filter-action-featured']) && $c4d_plugin_manager['woo-badge-auto-filter-action-featured']) {
		$content .= do_shortcode('[c4d-woo-badge-featured]');	
	}
	return $content;
}

function c4d_woo_badge_sale($product, $params) {
	global $c4d_plugin_manager;
	// out of stock or sold product
	if (! $product->is_in_stock()) return '<span class="c4d-woo-badge__sold">'.esc_html__('Sold', 'c4d-woo-badge').'</span>';

    $sale_price = get_post_meta( $product->id, '_price', true);
    $regular_price = get_post_meta( $product->id, '_regular_price', true);

    if (empty($regular_price)){ //then this is a variable product
        $available_variations = $product->get_available_variations();
        $variation_id = $available_variations[0]['variation_id'];
        $variation = new WC_Product_Variation( $variation_id );
        $regular_price = $variation ->regular_price;
        $sale_price = $variation ->sale_price;
    }

    $sale = '-'.ceil(( ($regular_price - $sale_price) / $regular_price ) * 100) . '%'; 
    $style = '';

    if ($c4d_plugin_manager) { // when use with plugin manager option, 
    	$type = isset($c4d_plugin_manager['woo-badge-sale-display-type']) ? $c4d_plugin_manager['woo-badge-sale-display-type'] : 'percent';
    	$type = isset($params['type']) ? $params['type'] : $type;
    	if ($type == 'percent') {
    		$sale = '-'.ceil(( ($regular_price - $sale_price) / $regular_price ) * 100) . '%'; 	
    	}
    	if ($type == 'amount') {
    		$sale = '-'.get_woocommerce_currency_symbol().($regular_price - $sale_price); 	
    	}
    	if ($type == 'text') {
    		$sale = isset($c4d_plugin_manager['woo-badge-sale-text']) ? $c4d_plugin_manager['woo-badge-sale-text'] : __('Sale', 'c4d-woo-badge');
    		$sale = isset($params['text']) ? $params['text'] : $sale;
    	}

    	$style = isset($c4d_plugin_manager['woo-badge-sale-color']) ? 'background:'.$c4d_plugin_manager['woo-badge-sale-color'].';' : 'background:#e5605d;';
    	$style .= isset($c4d_plugin_manager['woo-badge-sale-text-color']) ? 'color:'.$c4d_plugin_manager['woo-badge-sale-text-color'].';' : 'color:#fff;';
	}

	// use shortcode params
	if (isset($params['type']) && $params['type'] == 'text' && isset($params['text'])) {
		$sale = isset($params['text']) ? $params['text'] : $sale;
	}

	$style .= isset($params['background']) ? 'background:'.$params['background'].';' : '';
	$style .= isset($params['text_color']) ? 'color:'.$params['text_color'].';' : '';

	$content = '<span style="'.esc_attr($style).'" class="c4d-woo-badge__sale onsale">'.$sale.'</span>';
    
    return $content;
}
function c4d_woo_badge_shortcode_featured($params, $content) {
	global $product, $c4d_plugin_manager;
	$html = '';
	if($product->is_featured()) {
		$text 	= __('Featured', 'c4d-woo-badge');
		$style  = '';
		
		if ($c4d_plugin_manager) {
			$style = isset($c4d_plugin_manager['woo-badge-featured-color']) ? 'background:'.$c4d_plugin_manager['woo-badge-featured-color'].';' : 'background:#e5605d;';
	    	$style .= isset($c4d_plugin_manager['woo-badge-featured-text-color']) ? 'color:'.$c4d_plugin_manager['woo-badge-featured-text-color'].';' : 'color:#fff;';
		}
		
		$text = isset($params['text']) ? $params['text'] : $text;
		$style .= isset($params['background']) ? 'background:'.$params['background'].';' : '';
		$style .= isset($params['text_color']) ? 'color:'.$params['text_color'].';' : '';
		$html .= '<span style="'.esc_attr($style).'" class="c4d-woo-badge__featured">'.$text.'</span>';
	}
	return $html;
}

function c4d_woo_badge_shortcode_sale($params, $content) {
	global $product;
	$html = c4d_woo_badge_sale($product, $params);
	return $html;
}

function c4d_woo_badge_shortcode_news($params, $content) {
	global $c4d_plugin_manager;
	$html 	= '';
	$day 	= 10;
	$text 	= __('New', 'c4d-woo-badge');
	$style  = '';

	if ($c4d_plugin_manager) {
		$day = isset($c4d_plugin_manager['woo-badge-new-day']) ? $c4d_plugin_manager['woo-badge-new-day'] : $day;
		$dateposted	= get_the_time( 'Y-m-d' );			// Post date
		$dateposted = '2016-12-01';
		$timestampposted 	= strtotime( $dateposted );
		if( (time() - ( 60 * 60 * 24 * $day ) ) < $timestampposted ){
			$text = isset($c4d_plugin_manager['woo-badge-new-text']) ? $c4d_plugin_manager['woo-badge-new-text'] : $text;
		} 
		$style = isset($c4d_plugin_manager['woo-badge-new-color']) ? 'background:'.$c4d_plugin_manager['woo-badge-new-color'].';' : 'background:#e5605d;';
    	$style .= isset($c4d_plugin_manager['woo-badge-new-text-color']) ? 'color:'.$c4d_plugin_manager['woo-badge-new-text-color'].';' : 'color:#fff;';
	}
	
	$text = isset($params['text']) ? $params['text'] : $text;
	$style .= isset($params['background']) ? 'background:'.$params['background'].';' : '';
	$style .= isset($params['text_color']) ? 'color:'.$params['text_color'].';' : '';
	$html = '<span style="'.esc_attr($style).'" class="c4d-woo-badge__new">'.$text.'</span>';
	
	return $html;
}
function c4d_woo_badge_shortcode_popular($params, $content) {
	global $c4d_plugin_manager;
	$html 	= '';
	$text 	= __('Popular', 'c4d-woo-badge');
	$style  = '';

	if ($c4d_plugin_manager) {
		$day = isset($c4d_plugin_manager['woo-badge-new-day']) ? $c4d_plugin_manager['woo-badge-new-day'] : $day;
		$dateposted	= get_the_time( 'Y-m-d' );			// Post date
		$dateposted = '2016-12-01';
		$timestampposted 	= strtotime( $dateposted );
		if( (time() - ( 60 * 60 * 24 * $day ) ) < $timestampposted ){
			$text = isset($c4d_plugin_manager['woo-badge-new-text']) ? $c4d_plugin_manager['woo-badge-new-text'] : $text;
		} 
		$style = isset($c4d_plugin_manager['woo-badge-new-color']) ? 'background:'.$c4d_plugin_manager['woo-badge-new-color'].';' : 'background:#e5605d;';
    	$style .= isset($c4d_plugin_manager['woo-badge-new-text-color']) ? 'color:'.$c4d_plugin_manager['woo-badge-new-text-color'].';' : 'color:#fff;';
	}
	
	$text = isset($params['text']) ? $params['text'] : $text;
	$style .= isset($params['background']) ? 'background:'.$params['background'].';' : '';
	$style .= isset($params['text_color']) ? 'color:'.$params['text_color'].';' : '';
	$html = '<span style="'.esc_attr($style).'" class="c4d-woo-badge__new">'.$text.'</span>';
	
	return $html;
}

function c4d_woo_badge_section_options(){
	$opt_name = 'c4d_plugin_manager';
	Redux::setSection( $opt_name, array(
        'title'            => __( 'Woo Badge', 'c4d-woo-badge' ),
        'id'               => 'section-woo-badge',
        'desc'             => '',
        'customizer_width' => '400px',
        'icon'             => 'el el-home'
    ));
    Redux::setSection( $opt_name, array(
        'title'            => __( 'Sale Badge', 'c4d-woo-badge' ),
        'id'               => 'section-woo-badge-sale',
        'desc'             => '',
        'customizer_width' => '400px',
        
        'subsection' 	   => true,
        'fields'           => array(
        	array(
                'id'       => 'woo-badge-auto-filter-action-onsale',
                'type'     => 'switch',
                'title'    => __( 'Auto add filter action', 'c4d-woo-badge' ),
                'subtitle' => __( 'Auto add filter action to woocommerce_sale_flash or you can disable it to use shortcode where you want.', 'c4d-woo-badge' ),
                'default'  => true
            ),
        	array(
                'id'       => 'woo-badge-sale-text',
                'type'     => 'text',
                'title'    => __( 'Sale Text', 'c4d-woo-badge' ),
                'subtitle' => __( 'Set sale text when display sale by text', 'c4d-woo-badge' ),
                'desc'	   => __('text attribute in shortcode'),
                'default'  => __('Sale', 'c4d-woo-badge')
            ),
            array(
                'id'       => 'woo-badge-sale-display-type',
                'type'     => 'button_set',
                'title'    => __( 'Sale Display Type', 'c4d-woo-badge' ),
                'subtitle' => __( 'Set display type for sale badge like text, percent or amount', 'c4d-woo-badge' ),
                'desc'	   => __('type attribute in shortcode'),
                'options'  => array(
                    'text' => __('Text', 'c4d-woo-badge'),
                    'percent' => __('Percent', 'c4d-woo-badge'),
                    'amount' => __('Amount', 'c4d-woo-badge')
                ),
                'default'  => 'percent'
            ),
            array(
                'id'       => 'woo-badge-sale-color',
                'type'     => 'color',
                'title'    => __( 'Background color', 'c4d-woo-badge' ),
                'subtitle' => __( 'Pick a background color for the sale badge', 'c4d-woo-badge' ),
                'default'  => '#e5605d'
            ),
            array(
                'id'       => 'woo-badge-sale-text-color',
                'type'     => 'color',
                'title'    => __( 'Text color', 'c4d-woo-badge' ),
                'subtitle' => __( 'Pick a text color for the sale badge', 'c4d-woo-badge' ),
                'desc'	   => __('text_color attribute in shortcode'),
                'default'  => '#ffffff'
            )
        )
    ));
    Redux::setSection( $opt_name, array(
        'title'            => __( 'New Badge', 'c4d-woo-badge' ),
        'id'               => 'section-woo-badge-new',
        'desc'             => '',
        'customizer_width' => '400px',
        
        'subsection' 	   => true,
        'fields'           => array(
        	array(
                'id'       => 'woo-badge-auto-filter-action-new',
                'type'     => 'switch',
                'title'    => __( 'Auto add', 'c4d-woo-badge' ),
                'subtitle' => __( 'Auto add new badge to woocommerce_sale_flash or you can disable it to use shortcode where you want.', 'c4d-woo-badge' ),
                'default'  => true
            ),
        	array(
                'id'       => 'woo-badge-new-day',
                'type'     => 'text',
                'title'    => __( 'New Days', 'c4d-woo-badge' ),
                'subtitle' => __( 'Set days to calculate product is new', 'c4d-woo-badge' ),
                'desc'	   => '',
                'validate' => 'numeric',
                'default'  => '10'
            ),
            array(
                'id'       => 'woo-badge-new-color',
                'type'     => 'color',
                'title'    => __( 'Background color', 'c4d-woo-badge' ),
                'subtitle' => __( 'Pick a background color for the new badge', 'c4d-woo-badge' ),
                'default'  => '#e5605d'
            ),
            array(
                'id'       => 'woo-badge-new-text-color',
                'type'     => 'color',
                'title'    => __( 'Text color', 'c4d-woo-badge' ),
                'subtitle' => __( 'Pick a text color for the new badge', 'c4d-woo-badge' ),
                'desc'	   => __('text_color attribute in shortcode'),
                'default'  => '#ffffff'
            )
        )
    ));
    Redux::setSection( $opt_name, array(
        'title'            => __( 'Featured Badge', 'c4d-woo-badge' ),
        'id'               => 'section-woo-badge-featured',
        'desc'             => '',
        'customizer_width' => '400px',
        
        'subsection' 	   => true,
        'fields'           => array(
        	array(
                'id'       => 'woo-badge-auto-filter-action-featured',
                'type'     => 'switch',
                'title'    => __( 'Auto add', 'c4d-woo-badge' ),
                'subtitle' => __( 'Auto add new badge to woocommerce_sale_flash or you can disable it to use shortcode where you want.', 'c4d-woo-badge' ),
                'default'  => true
            ),
        	array(
                'id'       => 'woo-badge-featured-color',
                'type'     => 'color',
                'title'    => __( 'Background color', 'c4d-woo-badge' ),
                'subtitle' => __( 'Pick a background color for the new badge', 'c4d-woo-badge' ),
                'default'  => '#e5605d'
            ),
            array(
                'id'       => 'woo-badge-featured-text-color',
                'type'     => 'color',
                'title'    => __( 'Text color', 'c4d-woo-badge' ),
                'subtitle' => __( 'Pick a text color for the new badge', 'c4d-woo-badge' ),
                'desc'	   => __('text_color attribute in shortcode'),
                'default'  => '#ffffff'
            )
        )
    ));
}