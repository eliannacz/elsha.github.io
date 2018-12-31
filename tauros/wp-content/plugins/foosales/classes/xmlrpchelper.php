<?php if ( ! defined( 'ABSPATH' ) ) exit; 

class FooSales_XMLRPC_Helper
{
    public $Config;
    
    public function __construct($Config)
    {
        $this->Config = $Config;
        
        add_action('admin_notices', array($this, 'check_xmlrpc_enabled'));
    }
    
    public function check_xmlrpc_enabled()
    {
        if ( !$this->is_xmlrpc_enabled() )
        {
            $this->output_notices(array("XMLRPC is not enabled."));
        }
    }
    
    public function is_xmlrpc_enabled()
    {
        $return_bool = false; 
        $enabled = get_option('enable_xmlrpc');

        if ( $enabled )
        {
            $return_bool = true;
        }
        else
        {
            global $wp_version;
            
            if ( version_compare($wp_version, '3.5', '>=') )
            {
                $return_bool = true; 
            }
            else
            {
                $return_bool = false;
            }
        }
        
        return $return_bool;
    }
    
    private function output_notices($notices)
    {
        foreach ( $notices as $notice )
        {
            echo "<div class='updated'><p>$notice</p></div>";
        }
    }
}

function fsfwc_do_compare_categories($a, $b)
{
    return strcmp($a['pcn'], $b['pcn']);
}

function fsfwc_do_get_all_product_categories()
{
    $cats = get_terms('product_cat');
    
    $temp_categories = array();
    
    foreach ( $cats as $cat )
    {
        $category = array();
        
        $category['pcid'] = (string)$cat->term_id;
        
        $temp_display_name = '';
        
        if ( $cat->parent > 0 )
        {
            foreach ( $cats as $parent_cat )
            {
                if ( $parent_cat->term_id == $cat->parent )
                {
                    $temp_display_name .= htmlspecialchars_decode($parent_cat->name) . ' - ';
                    
                    break;
                }
            }
        }
        
        $temp_display_name .= htmlspecialchars_decode($cat->name);
        
        $category['pcn'] = (string)$temp_display_name;
        
        $temp_categories[] = $category;
        
        $category = null;
        $temp_display_name = null;
        
        unset($category, $temp_display_name);
    }
    
    uasort($temp_categories, 'fsfwc_do_compare_categories');
    
    $categories = array(); 
    
    foreach ( $temp_categories as $key => $category )
    {
        $categories[] = $category;
    }
    
    $cats = null;
    $temp_categories = null;
    
    unset($cats, $temp_categories);
    
    echo '"categories":' . json_encode($categories);
    
    flush();
    
    $categories = null;
    
    unset($categories);
}

function fsfwc_get_timezone()
{
    $timezone_string = get_option('timezone_string');

    if ( ! empty($timezone_string) )
    {
        return $timezone_string;
    }

    $offset = get_option('gmt_offset') * 60 * 60;
    
    $tzlist = DateTimeZone::listAbbreviations();
    
    foreach ( $tzlist as $tz_group )
    {
        foreach ( $tz_group as $tz )
        {
            if ( $tz['offset'] === $offset )
            {
                return $tz['timezone_id'];
            }
        }
    }
    
    return date_default_timezone_get();
}

function fsfwc_do_output_single_order(&$wc_order)
{
    date_default_timezone_set(fsfwc_get_timezone());
    
    if ( add_post_meta($wc_order->id, '_foosales_order_source', 'foosales_app', true) !== false )
    {
        delete_post_meta($wc_order->id, 'Order Source');
    }
    
    echo '{';
    
    flush();
    
    echo '"oid":"' . (string)$wc_order->id . '",';
    echo '"od":"' . (string)strtotime($wc_order->order_date) . '",';
    echo '"os":"' . (string)$wc_order->get_status() . '",';
    echo '"ost":"' . (string)$wc_order->get_subtotal() . '",';
    echo '"ot":"' . (string)$wc_order->get_total() . '",';
    
    $payment_method_key = (string)get_post_meta($wc_order->id, '_foosales_payment_method', true);
    
    if ( $payment_method_key == '' )
    {
        $payment_method = (string)get_post_meta($wc_order->id, 'Order Payment Method', true);
        
        if ( $payment_method == 'Cash' )
        {
            $payment_method_key = 'foosales_cash';
        }
        elseif ( $payment_method == 'Card' )
        {
            $payment_method_key = 'foosales_card';
        }
        
        $payment_method = null;
        
        unset($payment_method);
        
        add_post_meta($wc_order->id, '_foosales_payment_method', $payment_method_key, true);
    }
    
    echo '"opmk":"' . $payment_method_key . '",';
    
    $payment_method_key = null;
    
    unset($payment_method_key);
    
    echo '"ort":"' . (string)($wc_order->get_total_refunded() == "" ? "0" : $wc_order->get_total_refunded()) . '",';
    
    flush();
   
    $order_refunds = $wc_order->get_refunds();
    
    $order_refund_items = array();
    
    foreach ( $order_refunds as $order_refund )
    {
        $refund_items = $order_refund->get_items();
        
        foreach ( $refund_items as $refund_item )
        {
            $order_item_id = '';
            
            $meta_data = $refund_item->get_meta_data();
            
            foreach ( $meta_data as $meta_data_item )
            {
                if ( $meta_data_item->key == '_refunded_item_id' )
                {
                    $order_item_id = (string)$meta_data_item->value;
                    
                    break;
                }
            }
            
            if ( empty($order_refund_items[$order_item_id]) )
            {
                $order_refund_items[$order_item_id] = array(
                    'qty' => 0,
                    'total' => 0
                );
            }
            
            $order_refund_items[$order_item_id]['qty'] += abs($refund_item->get_quantity());
            $order_refund_items[$order_item_id]['total'] += abs($refund_item['total']);
        }
    }
    
    $order_refunds = null;
    
    echo '"oc":';
    
    $customer_data = array();
    
    $customer = get_userdata($wc_order->customer_id); 
    
    $customer_data['cid'] = (string)$customer->ID;
    $customer_data['cfn'] = (string)$customer->first_name;
    $customer_data['cln'] = (string)$customer->last_name;
    $customer_data['ce'] = (string)$customer->user_email;
    
    $customer = null;
    
    unset($customer);
    
    $customer_data['cbfn'] = (string)$wc_order->get_billing_first_name();
    $customer_data['cbln'] = (string)$wc_order->get_billing_last_name();
    $customer_data['cbco'] = (string)$wc_order->get_billing_company();
    $customer_data['cba1'] = (string)$wc_order->get_billing_address_1();
    $customer_data['cba2'] = (string)$wc_order->get_billing_address_2();
    $customer_data['cbc'] = (string)$wc_order->get_billing_city();
    $customer_data['cbpo'] = (string)$wc_order->get_billing_postcode();
    $customer_data['cbcu'] = (string)$wc_order->get_billing_country();
    $customer_data['cbs'] = (string)$wc_order->get_billing_state();
    $customer_data['cbph'] = (string)$wc_order->get_billing_phone();
    $customer_data['cbe'] = (string)$wc_order->get_billing_email();
    $customer_data['csfn'] = (string)$wc_order->get_shipping_first_name();
    $customer_data['csln'] = (string)$wc_order->get_shipping_last_name();
    $customer_data['csco'] = (string)$wc_order->get_shipping_company();
    $customer_data['csa1'] = (string)$wc_order->get_shipping_address_1();
    $customer_data['csa2'] = (string)$wc_order->get_shipping_address_2();
    $customer_data['csc'] = (string)$wc_order->get_shipping_city();
    $customer_data['cspo'] = (string)$wc_order->get_shipping_postcode();
    $customer_data['cscu'] = (string)$wc_order->get_shipping_country();
    $customer_data['css'] = (string)$wc_order->get_shipping_state();
    
    echo json_encode($customer_data);
    
    $customer_data = null;
    
    unset($customer_data);
    
    flush();
    
    echo ',"oi":[';
    
    flush();
    
    $wc_order_items = $wc_order->get_items();
    
    $last_wc_order_item = end($wc_order_items);
    
    foreach ( $wc_order_items as $wc_order_item )
    {
        $product_id = $wc_order_item['product_id'];
        
        if ( (int)$wc_order_item['variation_id'] > 0 )
        {
            $product_id = (int)$wc_order_item['variation_id'];
        }
        
        echo '{';
        
        echo '"oiid":"' . (string)$wc_order_item->get_id() . '",';
        echo '"oipid":"' . (string)$product_id . '",';
        echo '"oilst":"' . (string)$wc_order_item['line_subtotal'] . '",';
        echo '"oilstt":"' . (string)$wc_order_item['line_subtotal_tax'] . '",';
        echo '"oiltx":"' . (string)$wc_order_item['total_tax'] . '",';
        echo '"oiltl":"' . (string)$wc_order_item['total'] . '",';
        echo '"oiq":"' . (string)$wc_order_item['qty'] . '",';
        
        $refunded_quantity = "0";
        $refunded_total = "0";
        
        if ( !empty($order_refund_items[(string)$wc_order_item->get_id()]) )
        {
            $refunded_quantity = (string)$order_refund_items[(string)$wc_order_item->get_id()]['qty'];
            $refunded_total = (string)$order_refund_items[(string)$wc_order_item->get_id()]['total'];
        }
        
        echo '"oirq":"' . $refunded_quantity . '",';
        echo '"oirt":"' . $refunded_total . '"';
        
        echo '}';
        
        if ( $wc_order_item != $last_wc_order_item )
        {
            echo ',';
        }
        
        $product_id = null;
        
        unset($product_id);
        
        flush();
    }
    
    echo ']';
    
    $wc_order_items = null;
    $last_wc_order_item = null;
    $order_refund_items = null;
    
    unset($last_wc_order_item, $wc_order_items, $order_refunds, $order_refund_items);
    
    flush();
    
    echo ',"odi":[';
    
    $coupons = $wc_order->get_items('coupon');
    
    if ( !empty($coupons) )
    {
        $last_coupon = end($coupons);
        
        foreach ( $coupons as $coupon )
        {
            echo '{"odicc":"' . $coupon->get_code() . '", "odid":"' . $coupon->get_discount() . '", "odidt":"' . $coupon->get_discount_tax() . '"}';
            
            if ( $coupon != $last_coupon )
            {
                echo ',';
                
                flush();
            }
        }
    }
    
    echo ']';
    
    flush();
    
    $wc_order = null;
    
    unset($wc_order);
    
    echo '}';
    
    flush();
}

function fsfwc_do_get_single_product($post_id, &$wc_tax, &$cat_names, $shop_tax = 'incl')
{
    $wc_product = wc_get_product($post_id);
    
    echo '{';
    
    echo '"pid":"' . (string)$post_id . '",';
    
    $product_title = utf8_encode(json_encode((string)get_post_field('post_title', $post_id)));
    
    echo '"pt":' . $product_title . ',';
    
    $price = $wc_product->price;
    
    echo '"ppi":"' . (string)wc_get_price_including_tax($wc_product, array('price' => $price)) . '",';
    echo '"ppe":"' . (string)wc_get_price_excluding_tax($wc_product, array('price' => $price)) . '",';
    
    $price = null;
    
    unset($price);
    
    $regular_price = $wc_product->regular_price;
    
    echo '"prpi":"' . (string)wc_get_price_including_tax($wc_product, array('price' => $regular_price)) . '",';
    echo '"prpe":"' . (string)wc_get_price_excluding_tax($wc_product, array('price' => $regular_price)) . '",';
    
    $regular_price = null;
    
    unset($regular_price);
    
    $sale_price = $wc_product->sale_price;
    
    echo '"pspi":"' . (string)wc_get_price_including_tax($wc_product, array('price' => $sale_price)) . '",';
    echo '"pspe":"' . (string)wc_get_price_excluding_tax($wc_product, array('price' => $sale_price)) . '",';
    
    $sale_price = null;
    
    unset($sale_price);
    
    flush();
    
    $tax_rate = 0.0; 
    
    $tax_rates = $wc_tax->get_rates_for_tax_class($wc_product->get_tax_class());
    
    if ( !empty($tax_rates) )
    {
        $tax_rate_item = reset($tax_rates);
    
        $tax_rate = (string)$tax_rate_item->tax_rate;
        
        $tax_rate_item = null;
        
        unset($tax_rate_item);
    }
    
    $tax_rates = null;
    
    unset($tax_rates);
    
    echo '"ptr":"' . (string)$tax_rate . '",';
    
    $tax_rate = null;
    
    unset($tax_rate);
    
    echo '"psm":"' . ($wc_product->manage_stock == 'yes' ? "1" : "0") . '",';
    echo '"ps":"' . ($wc_product->get_stock_quantity() != null ? (string)$wc_product->get_stock_quantity() : "0") . '",';
    echo '"psku":' . json_encode((string)$wc_product->get_sku()) . ',';
    echo '"psi":"' . ($wc_product->sold_individually == "yes" ? "1" : "0") . '",';
    
    flush();
    
    echo '"pv":['; 
    
    if( $wc_product->is_type( 'variable' ) )
    {
        $atts = $wc_product->get_variation_attributes();
        
        $attributes = array();
        
        foreach ( $atts as $att_name => $att_val )
        {
            $attributes[] = $att_name;
        }
        
        $atts = null;
        
        unset($atts);
        
        $variations = $wc_product->get_available_variations();
        
        $last_variation = end($variations);
        
        foreach ( $variations as $variation )
        {
            echo '{';
            
            echo '"pvid":"' . $variation['variation_id'] . '",';
            echo '"pt":' . $product_title . ',';
            
            $variation_attributes = '';
            $variation_attribute_count = 0;
            
            foreach ( $variation['attributes'] as $variation_attribute_key => $variation_attribute_value )
            {
                $variation_attributes .= $attributes[$variation_attribute_count++] . ': ' . $variation_attribute_value;
                
                if ( $variation_attribute_count < count($variation['attributes']) )
                {
                    $variation_attributes .= ' | ';
                }
            }
            
            echo '"pva":' . json_encode($variation_attributes) . ',';
            
            $wc_product_variation = wc_get_product($variation['variation_id']);
            
            $tax_rate = 0.0;
            
            $tax_rates = $wc_tax->get_rates_for_tax_class($wc_product_variation->get_tax_class());
            
            if ( !empty($tax_rates) )
            {
                $tax_rate_item = reset($tax_rates);
            
                $tax_rate = (float)$tax_rate_item->tax_rate;
                
                $tax_rate_item = null;
                
                unset($tax_rate_item);
            }
            
            echo '"ptr":"' . (string)$tax_rate . '",';
            
            $tax_rate = null;
            
            unset($tax_rate);
            
            $price = $wc_product_variation->price;
            
            echo '"ppi":"' . (string)wc_get_price_including_tax($wc_product_variation, array('price' => $price)) . '",';
            echo '"ppe":"' . (string)wc_get_price_excluding_tax($wc_product_variation, array('price' => $price)) . '",';
            
            $price = null;
            
            unset($price);
            
            $regular_price = $wc_product_variation->regular_price;
            
            echo '"prpi":"' . (string)wc_get_price_including_tax($wc_product_variation, array('price' => $regular_price)) . '",';
            echo '"prpe":"' . (string)wc_get_price_excluding_tax($wc_product_variation, array('price' => $regular_price)) . '",';
            
            $regular_price = null;
            
            unset($regular_price);
            
            $sale_price = $wc_product_variation->sale_price;
            
            echo '"pspi":"' . (string)wc_get_price_including_tax($wc_product_variation, array('price' => $sale_price)) . '",';
            echo '"pspe":"' . (string)wc_get_price_excluding_tax($wc_product_variation, array('price' => $sale_price)) . '",';
            
            $sale_price = null;
            
            unset($sale_price);
            
            flush();
            
            echo '"pi":"' . (string)$variation['image']['thumb_src'] . '",';
            echo '"psm":"' . ($wc_product_variation->manage_stock == 'yes' ? "1" : "0") . '",';
            
            echo '"ps":"' . ($wc_product_variation->get_stock_quantity() != null ? (string)$wc_product_variation->get_stock_quantity() : "0") . '",';
            echo '"psku":' . json_encode((string)$wc_product_variation->get_sku());
            
            echo '}';
            
            if ( $variation != $last_variation )
            {
                echo ',';
            }
            
            $wc_product_variation = null;
            
            unset($wc_product_variation);
            
            flush();
        }
    }
    
    echo '],';
    
    flush();
    
    $wc_product = null;
    
    unset($wc_product);
    
    echo '"pc":[';
    
    $cat_ids = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'ids'));
    
    $last_cat_id = end($cat_ids);
    
    foreach ( $cat_ids as $cat_id )
    {
        echo '{';
        
        if ( empty($cat_names[(string)$cat_id]) )
        {
            $cat = get_term_by('id', $cat_id, 'product_cat');
            
            $cat_names[(string)$cat_id] = utf8_encode(htmlspecialchars_decode($cat->name));
            
            $cat = null;
            
            unset($cat);
        }
        
        echo '"pcid":"' . (string)$cat_id . '",';
        echo '"pcn":' . json_encode((string)$cat_names[(string)$cat_id]);
        
        echo '}';
        
        if ( $cat_id != $last_cat_id )
        {
            echo ',';
        }
        
        flush();
    }
    
    echo ']';
    
    $cat_ids = null;
    $last_cat_id = null;
    
    unset($cat_ids, $last_cat_id);
    
    // Check if FooEvents plugin is enabled
    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
    }
    
    if ( get_post_meta($post_id, "WooCommerceEventsEvent", true) == "Event" && (foosales_check_plugin_active('fooevents/fooevents.php') || is_plugin_active_for_network('fooevents/fooevents.php')) )
    {
        echo ',"fee":';
        
        $event = array();
        
        $event['cad'] = (get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeDetails', true) == 'on' ? '1' : '0');
        $event['cat'] = (get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeTelephone', true) == 'on' ? '1' : '0');
        $event['cac'] = (get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeCompany', true) == 'on' ? '1' : '0');
        $event['cades'] = (get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeDesignation', true) == 'on' ? '1' : '0');
        
        $custom_fields = array();
        
        if ( foosales_check_plugin_active('fooevents_custom_attendee_fields/fooevents-custom-attendee-fields.php') || is_plugin_active_for_network('fooevents_custom_attendee_fields/fooevents-custom-attendee-fields.php') )
        {
            $custom_fields_value = trim(get_post_meta($post_id, 'fooevents_custom_attendee_fields_options_serialized', true));
            
            if ( $custom_fields_value != '' )
            {
                $custom_fields_data = json_decode($custom_fields_value, true);
                
                foreach ( $custom_fields_data as $option => $custom_field_value )
                {
                    $custom_field = array();
                    
                    $key_prefix = substr($option, 0, strlen($event) - strlen('_option'));
                    
                    foreach ( $custom_field_value as $key => $value )
                    {
                        $new_key = str_replace($key_prefix . "_", "", $key);
                        $new_value = $value;
                        
                        if ( $new_key == 'req' )
                        {
                            $new_value = $value == 'true' ? '1' : '0';
                        }
                        
                        $custom_field[$new_key] = $new_value;
                    }
                    
                    $custom_fields[] = $custom_field;
                    
                    $custom_field = null;
                    
                    unset($custom_field);
                }
            }
        }
        
        $event['caf'] = $custom_fields;
        
        echo json_encode($event);
        
        flush();
        
        $custom_fields = null;
        $event = null;
        
        unset($custom_fields, $event);
    }
    
    echo '}';
    
    flush();
}

function fsfwc_output_single_customer($id)
{
    $customer_data = array();
    
    $customer = get_userdata($id); 
    
    $customer_data['cid'] = (string)$id;
    $customer_data['cfn'] = $customer->first_name;
    $customer_data['cln'] = $customer->last_name;
    $customer_data['ce'] = $customer->user_email;
    
    $customer = null;
    
    unset($customer);
    
    $customer_fields = array(
        'cbfn' => 'billing_first_name',
        'cbln' => 'billing_last_name',
        'cbco' => 'billing_company',
        'cba1' => 'billing_address_1',
        'cba2' => 'billing_address_2',
        'cbc' => 'billing_city',
        'cbpo' => 'billing_postcode',
        'cbcu' => 'billing_country',
        'cbs' => 'billing_state',
        'cbph' => 'billing_phone',
        'cbe' => 'billing_email',
        'csfn' => 'shipping_first_name',
        'csln' => 'shipping_last_name',
        'csco' => 'shipping_company',
        'csa1' => 'shipping_address_1',
        'csa2' => 'shipping_address_2',
        'csc' => 'shipping_city',
        'cspo' => 'shipping_postcode',
        'cscu' => 'shipping_country',
        'css' => 'shipping_state'
    );
    
    $customer_meta = get_user_meta($id);
    
    foreach ( $customer_fields as $customer_key => $meta_key )
    {
        $val = '';
        
        if ( !empty($customer_meta[$meta_key]) )
        {
            $val = $customer_meta[$meta_key][0];
        }
        
        $customer_data[$customer_key] = $val;
    }
    
    echo json_encode($customer_data);
    
    $customer_data = null;
    $customer_meta = null;
    
    unset($customer_data, $customer_meta);
    
    flush();
}

function fsfwc_do_get_all_customers($offset = 0)
{
    $max_users = 2000;
    
    $args = array(
        'role__in' => array('customer', 'subscriber'),
        'number' => $max_users,
        'offset' => $offset * $max_users,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby'=> 'user_email',
        'order' => 'ASC'
    );
    
    $query = new WP_User_Query($args);
    
    $args = null;
    
    unset($args);
    
    echo '"total_customers":"' . $query->total_users . '_total_customers",';
    echo '"customers":[';
    
    flush();
    
    $last_customer_id = end($query->results);
    
    foreach ( $query->results as $customer_id )
    {
        fsfwc_output_single_customer($customer_id);
        
        if ( $customer_id != $last_customer_id )
        {
            echo ',';
        }
    }
    
    $query = null;
    $last_customer = null;
    
    unset($query, $last_customer);
    
    echo ']';
    
    flush();
}

function fsfwc_do_get_all_orders($offset = 0)
{
    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'shop_order',
        'post_status' => array('wc-completed', 'wc-cancelled', 'wc-refunded'),
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key'   => '_foosales_order_source',
                'value' => 'foosales_app',
            ),
            array(
                'key'   => 'Order Source',
                'value' => 'FooSales app',
            )
        ),
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby'=> 'id',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($args);
    
    $args = null;
    
    unset($args);
    
    echo '"total_orders":"' . $query->post_count . '_total_orders",';
    echo '"orders":[';
    
    $last_post_id = end($query->posts);
    
    $max_orders = 200;
    $orders_start = ($offset * $max_orders) + 1;
    $orders_end = ($offset * $max_orders) + $max_orders;
    $order_count = 0;
    
    foreach ( $query->posts as $post_id )
    {
        $order_count++;
        
        if ( $order_count < $orders_start )
        {
            continue;
        }
        
        $wc_order = wc_get_order($post_id);
        
        fsfwc_do_output_single_order($wc_order);
        
        if ( $order_count == $orders_end )
        {
            break;
        }
        
        if ( $post_id != $last_post_id )
        {
            echo ',';
        }
        
        flush();
    }
    
    $query = null;
    $last_post_id = null;
    
    unset($query, $last_post_id);
    
    echo ']';
    
    flush();
}

function fsfwc_do_get_all_products($offset = 0)
{
    $max_products = 1000;
    
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $max_products,
        'offset' => $offset * $max_products,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby'=> 'title',
        'order' => 'ASC',
        'post_status' => array('publish', 'future', 'draft')
    );
    
    $query = new WP_Query($args);
    
    $args = null;
    
    unset($args);
    
    $product_posts = wp_count_posts('product');
    
    $total_products = (int)$product_posts->publish + (int)$product_posts->future + (int)$product_posts->draft;
    
    echo '"total_products":"' . $total_products . '_total_products",';
    echo '"sale_product_ids":"' . implode(",", wc_get_product_ids_on_sale()) . '",';
    echo '"products":[';
    
    $product_posts = null;
    $total_products = null;
    
    unset($product_posts, $total_products);
    
    flush();
    
    $wc_tax = new WC_Tax();
    
    $cat_names = array();
    
    $last_post_id = end($query->posts);
    
    $shop_tax = ((string)get_option('woocommerce_calc_taxes', '') == "yes") ? (string)get_option('woocommerce_tax_display_shop', '') : "incl"; 
    
    foreach ( $query->posts as $post_id )
    {
        fsfwc_do_get_single_product($post_id, $wc_tax, $cat_names, $shop_tax);
        
        if ( $post_id != $last_post_id )
        {
            echo ',';
        }
        
        flush();
    }
    
    $query = null;
    $cat_names = null;
    $wc_tax = null;
    $last_post_id = null;
    
    unset($query, $cat_names, $wc_tax, $last_post_id);
    
    echo ']';
    
    flush();
}

function fsfwc_do_get_all_product_images()
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby'=> 'title',
        'order' => 'ASC',
        'post_status' => array('publish', 'future')
    );
    
    $query = new WP_Query($args);
    
    $args = null;
    
    unset($args);
    
    echo '"total_product_images":"' . $query->post_count . '_total_product_images",';
    echo '"product_images":[';
    
    flush();
    
    $last_post_id = end($query->posts);
    
    foreach ( $query->posts as $post_id )
    {
        echo '{"pid":"' . (string)$post_id . '", "pi":"' . (string)get_the_post_thumbnail_url($post_id, 'thumbnail') . '"}';
        
        if ( $post_id != $last_post_id )
        {
            echo ',';
        }
        
        flush();
    }
    
    $query = null;
    $last_post_id = null;
    
    unset($query, $last_post_id);
    
    echo ']';
    
    flush();
}

/**
 * Tests whether or not XMLRPC is accessible
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_test_access($args)
{
    echo 'FooSales success';
    
    exit();
}

/**
 * Checks connection details and if successful, fetches all data
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_connect_data_fetch($args)
{
    $memory_limit = ini_get('memory_limit');
    
    ini_set('memory_limit', '-1');
    
    set_time_limit(0);
    
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';
    
    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        if ( !fsfwc_checkroles($user) )
        {
            $output['message'] = false;
            $output['invalid_user'] = "1";
            
            echo json_encode($output);
            
            exit();
        }
        
        $output['message'] = true;
        
        echo '{"message":true, "data":{';
        
        flush();
    }
    
    $chunk = $args[2];
    
    $data = array();
    
    if ( $chunk == 'store_settings' )
    {
        echo '"user":' . json_encode($user->data) . ',';
        
        flush();
        
        $temp_config = new FooSales_Config();
        
        echo '"plugin_version":"' . (string)$temp_config->pluginVersion . '",';
        
        $temp_config = null;
        
        unset($temp_config);
        
        flush();
        
        // Get app settings
        echo '"store_logo_url":' . json_encode(trim((string)get_option('globalFooSalesStoreLogoURL', ''))) . ',';
        echo '"store_name":' . json_encode(trim((string)get_option('globalFooSalesStoreName', ''))) . ',';
        echo '"receipt_header":' . json_encode(trim(str_replace("\r\n", '<br />', get_option('globalFooSalesHeaderContent', '')))) . ',';
        echo '"receipt_footer":' . json_encode(trim(str_replace("\r\n", '<br />', get_option('globalFooSalesFooterContent', '')))) . ',';
        
        flush();
        
        // Check if FooEvents plugin is enabled
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
        }

        echo '"fooevents_active":"';

        if (foosales_check_plugin_active('fooevents/fooevents.php') || is_plugin_active_for_network('fooevents/fooevents.php')) {

            echo '1';

        } else {

            echo '0';

        }
        
        echo '",';
        
        $settings = array
        (
            'cs' => mb_convert_encoding(html_entity_decode(get_woocommerce_currency_symbol()), "UTF-8"),
            'ct' => ((string)get_option('woocommerce_calc_taxes', '') == "yes" ? "1" : "0"),
            'pit' => ((string)get_option('woocommerce_prices_include_tax', '') == "yes" ? "1" : "0")
        );
        
        $settings['cpt'] = ($settings['ct'] == "1") ? (string)get_option('woocommerce_tax_display_cart', '') : "incl";
        $settings['spt'] = ($settings['ct'] == "1") ? (string)get_option('woocommerce_tax_display_shop', '') : "incl";
        
        $currency_format = mb_convert_encoding(html_entity_decode(get_woocommerce_price_format()), "UTF-8");
        $currency_format = str_replace('%1$s', $settings['cs'], $currency_format);
        $currency_format = str_replace('%2$s', '%@', $currency_format);
        
        $settings['cf'] = $currency_format;
        
        echo '"settings":' . json_encode($settings) . ',';
        
        flush();
        
        fsfwc_do_get_all_product_categories();
        
        flush();
    }
    else if ( strpos($chunk, 'customers') !== false )
    {
        fsfwc_do_get_all_customers((int)substr($chunk, strlen('customers')));
        
        flush();
    }
    else if ( strpos($chunk, 'orders') !== false )
    {
        fsfwc_do_get_all_orders((int)substr($chunk, strlen('orders')));
        
        flush();
    }
    elseif ( strpos($chunk, 'products') !== false )
    {
        fsfwc_do_get_all_products((int)substr($chunk, strlen('products')));
        
        flush();
    }
    elseif ( $chunk == 'product_images' )
    {
        fsfwc_do_get_all_product_images();
        
        flush();
    }
    
    echo '}}';
    
    flush();
    
    ini_set('memory_limit', $memory_limit);
    
    exit();
}

function fsfwc_do_update_product($product_params)
{
    $wc_product = wc_get_product($product_params[0]);
    
    $wc_product->set_price($product_params[1]);
    $wc_product->set_regular_price($product_params[2]);
    $wc_product->set_sale_price($product_params[3]);
    
    $wc_product->save();
    
    wc_update_product_stock($wc_product, $product_params[4]);
}

/**
 * Updates product price and stock quantity
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_update_product($args)
{
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['message'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    fsfwc_do_update_product(array($args[2], $args[3], $args[4], $args[5], $args[6]));
    
    echo json_encode(array('status' => 'success'));
    
    exit();
}

function fsfwc_do_create_new_order($order_data)
{
    global $woocommerce;
    
    // Check if FooEvents plugin is enabled
    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
    }
    
    $isFooEventsEnabled = false;
    
    if ( foosales_check_plugin_active('fooevents/fooevents.php') || is_plugin_active_for_network('fooevents/fooevents.php') )
    {
        $isFooEventsEnabled = true;
    }
    
    if ( $isFooEventsEnabled )
    {
        $woocommerce->cart = new WC_Cart();
    }
    
    $order_date = $order_data[0];
    $payment_method_key = $order_data[1];
    $coupons = json_decode(stripslashes($order_data[2]), true);
    $order_items = json_decode(stripslashes($order_data[3]), true);
    $order_customer = json_decode(stripslashes($order_data[4]), true);
    $attendee_details = json_decode(stripslashes($order_data[5]), true);
    
    date_default_timezone_set(fsfwc_get_timezone());
    
    $order = wc_create_order();
    
    // Order date
    wp_update_post(array(
        'ID' => $order->id,
        'post_date' => date('Y-m-d H:i:s', (int)$order_date)
    ));
    
    // Payment method
    add_post_meta($order->id, '_foosales_order_source', 'foosales_app', true);
    add_post_meta($order->id, '_foosales_payment_method', $payment_method_key, true);
    
    $payment_method = '';
    
    if ( $payment_method_key == 'foosales_cash' )
    {
        $payment_method = 'Cash';
    }
    elseif ( $payment_method_key == 'foosales_card' )
    {
        $payment_method = 'Card';
    }
    
    add_post_meta($order->id, 'Order Payment Method', $payment_method, true);
    
    // Order items
    foreach ( $order_items as $order_item )
    {
        $line_total_excl = $order_item['oilst'];
        
        $product_args = array(
            'totals' => array(
                'subtotal' => $line_total_excl,
                'total' => $line_total_excl
            )
        );
        
        $wc_product = wc_get_product($order_item['pid']);
        
        if ( $isFooEventsEnabled )
        {
            $variation_id = 0;
            $attributes = array();
            
            if ( $wc_product->get_type() == 'variation' )
            {
                $variation_id = $order_item['pid'];
                $attributes = $wc_product->get_attributes();
            }
            
            $woocommerce->cart->add_to_cart($order_item['pid'], $order_item['oiq'], $variation_id, $attributes);
        }
        
        $order->add_product($wc_product, $order_item['oiq'], $product_args);
        
        wc_update_product_stock($wc_product, $order_item['oiq'], 'decrease');
        
        $product_args = null;
        
        unset($product_args);
    }
    
    $order->calculate_totals();
    
    if ( !empty($coupons) )
    {
        foreach ( $coupons as $coupon )
        {
            $order->apply_coupon(new WC_Coupon($coupon));
        }
    }
    
    // Order customer
    if ( $order_customer['cid'] != '' && (int)$order_customer['cid'] > 0 )
    {
        $order->set_customer_id((int)$order_customer['cid']);
        
        if ( trim($order_customer['cbfn']) != '' )
        {
            $order->set_billing_first_name($order_customer['cbfn']);
        }
        else
        {
            $order->set_billing_first_name($order_customer['cfn']);
        }
        
        if ( trim($order_customer['cbln']) != '' )
        {
            $order->set_billing_last_name($order_customer['cbln']);
        }
        else
        {
            $order->set_billing_last_name($order_customer['cln']);
        }
        
        $order->set_billing_company($order_customer['cbco']);
        $order->set_billing_address_1($order_customer['cba1']);
        $order->set_billing_address_2($order_customer['cba2']);
        $order->set_billing_city($order_customer['cbc']);
        $order->set_billing_postcode($order_customer['cbpo']);  
        $order->set_billing_country($order_customer['cbcu']);
        $order->set_billing_state($order_customer['cbs']);
        $order->set_billing_phone($order_customer['cbph']);
        $order->set_billing_email($order_customer['cbe']);
        
        $order->set_shipping_first_name($order_customer['csfn']);
        $order->set_shipping_last_name($order_customer['csln']);
        $order->set_shipping_company($order_customer['csco']);
        $order->set_shipping_address_1($order_customer['csa1']);
        $order->set_shipping_address_2($order_customer['csa2']);
        $order->set_shipping_city($order_customer['csc']);
        $order->set_shipping_postcode($order_customer['cspo']);  
        $order->set_shipping_country($order_customer['cscu']);
        $order->set_shipping_state($order_customer['css']);
    }
    
    if ( $isFooEventsEnabled )
    {
        if ( !empty($attendee_details) )
        {
            foreach ( $attendee_details as $key => $val )
            {
                $_POST[$key] = $val;
            }
            
            $FooEventsConfig = new FooEvents_Config();

            //CheckoutHelper
            require_once($FooEventsConfig->classPath.'checkouthelper.php');
            $FooEventsCheckoutHelper = new FooEvents_Checkout_Helper($FooEventsConfig);
            
            $FooEventsCheckoutHelper->woocommerce_events_process($order->id);
        }
    }
    
    $order->update_status('completed', "", false);
    
    return $order;
}

/**
 * Creates a completed order
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_create_order($args)
{
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['message'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    $orderDate = $args[2];
    $paymentMethodKey = $args[3];
    $coupons = $args[4];
    $orderItems = $args[5];
    $orderCustomer = $args[6];
    
    $attendeeDetails = "";
    
    if ( !empty($args[7]) )
    {
        $attendeeDetails = $args[7];
    }
    
    $new_order = fsfwc_do_create_new_order(array($orderDate, $paymentMethodKey, $coupons, $orderItems, $orderCustomer, $attendeeDetails));
    
    echo '{"status":"success","order":';
    
    fsfwc_do_output_single_order($new_order);
    
    echo "}";
    
    flush();
    
    exit();
}

/**
 * Syncs offline data
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_sync_offline_changes($args)
{
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['message'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    $offline_changes = json_decode(stripslashes($args[2]), true);
    $new_order_ids = array();
    $new_orders = array();
    $cancelled_order_ids = array();
    
    $last_offline_change = end($offline_changes);
    
    foreach ( $offline_changes as $offline_change )
    {
        if ( !empty($offline_change['update_product']) )
        {
            fsfwc_do_update_product($offline_change['update_product']['FooSalesProductParams']);
            
            $response = array();
            
            $response['ocid'] = $offline_change['update_product']['ocid'];
            
            echo json_encode($response);
            
            flush();
        }
        elseif ( !empty($offline_change['create_order']) )
        {
            $order_params = $offline_change['create_order'];
            $temp_id = $order_params['temp_id'];
            
            $new_order = fsfwc_do_create_new_order(array($order_params['date'], $order_params['payment_method_key'], json_encode($order_params['coupons']), json_encode($order_params['items']), json_encode($order_params['customer']), json_encode($order_params['attendee_details'])));
            
            $new_order_ids[] = array
            (
                'temp_id' => (string)$temp_id,
                'oid' => (string)$new_order->id
            );
            
            $order_items = $new_order->get_items();
            
            $new_orders[(string)$temp_id] = array();
            
            $response = array();
            
            $response[(string)$temp_id] = array();
            
            foreach ( $order_items as $order_item )
            {
                $new_orders[(string)$temp_id][] = array
                (
                    'oiid' => (string)$order_item->get_id(),
                    'oipid' => (string)$order_item['product_id']
                );
                
                $response[(string)$temp_id][] = array
                (
                    'oiid' => (string)$order_item->get_id(),
                    'oipid' => (string)$order_item['product_id']
                );
            }
            
            $response['ocid'] = $offline_change['create_order']['ocid'];
            
            $response['newOrderID'] = array(
                'temp_id' => (string)$temp_id,
                'oid' => (string)$new_order->id
            );
            
            echo json_encode($response);
            
            flush();
        }
        elseif ( !empty($offline_change['cancel_order']) )
        {
            $cancel_order_params = $offline_change['cancel_order'];
            $temp_id = '';
            $cancel_id = $cancel_order_params['oid'];
            
            if ( strpos($cancel_id, '_') !== false )
            {
                $temp_id = $cancel_id;
                
                foreach ( $new_order_ids as $new_order_id )
                {
                    if ( $new_order_id['temp_id'] == $cancel_id )
                    {
                        $cancel_id = $new_order_id['oid'];
                    }
                }
            }
            
            fsfwc_do_cancel_order($cancel_id, (bool)$cancel_order_params['restock']);
            
            $cancelled_order_ids[] = array
            (
                'temp_id' => (string)$temp_id,
                'oid' => (string)$cancel_id,
                'restock' => $cancel_order_params['restock']
            );
            
            $response = array();
            
            $response['ocid'] = $offline_change['cancel_order']['ocid'];
            
            echo json_encode($response);
            
            flush();
        }
        elseif ( !empty($offline_change['refund_order']) )
        {
            $refund_order_params = $offline_change['refund_order'];
            $temp_id = $refund_order_params['oid'];
            $order_id = $refund_order_params['oid'];
            $refunded_items = json_decode(stripslashes($refund_order_params['refundedItems']), true);
            
            foreach ( $refunded_items as &$refunded_item )
            {
                if ( !empty($new_orders[$temp_id]) )
                {
                    foreach ( $new_orders[$temp_id] as $new_order_item )
                    {
                        if ( $new_order_item['oipid'] == $refunded_item['oipid'] )
                        {
                            $refunded_item['oiid'] = $new_order_item['oiid'];
                            
                            break;
                        }
                    }
                }
            }
            
            foreach ( $new_order_ids as $new_order_id )
            {
                if ( $new_order_id['temp_id'] == $temp_id )
                {
                    $order_id = $new_order_id['oid'];
                    
                    break;
                }
            }
            
            $refunded_order = fsfwc_do_refund_order($order_id, $refunded_items);
            
            $response = array();
            
            $response['ocid'] = $offline_change['refund_order']['ocid'];
            
            echo json_encode($response);
            
            flush();
        }
        
        if ( $offline_change != $last_offline_change )
        {
            echo "|";
            
            flush();
        }
    }
    
    echo 'FooSalesResponse:';
    
    flush();
    
    echo json_encode(array
    (
        'status' => 'success'
    ));
    
    exit();
}

function fsfwc_do_cancel_order($order_id, $restock)
{
    try
    {
        $wc_order = wc_get_order($order_id);
        
        $refund = wc_create_refund(array(
            'order_id' => $order_id,
            'amount' => $wc_order->get_total() - $wc_order->get_total_refunded()
        ));
        
        if ( $restock )
        {
            $wc_order_items = $wc_order->get_items();
            
            foreach ( $wc_order_items as $wc_order_item )
            {
                $wc_product = wc_get_product($wc_order_item['product_id']);
                
                if ( $wc_product->manage_stock == 'yes' )
                {
                    if ( $wc_product->get_stock_quantity() == 0 )
                    {
                        $wc_product->set_stock_status('instock');
                    }
                    
                    wc_update_product_stock($wc_product, $wc_order_item->get_quantity(), 'increase');
                }
                
                $wc_product = null;
                
                unset($wc_product);
            }
        }
        
        if ( $wc_order->update_status('cancelled', "", false) )
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    catch ( Exception $e )
    {
        return false;
    }
    
    return false;
}

/**
 * Cancels an order, refunds the total and restocks if specified
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_cancel_order($args)
{
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['status'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    if ( fsfwc_do_cancel_order($args[2], (bool)$args[3]) )
    {
        echo json_encode(array('status' => 'success'));
    }
    else
    {
        echo json_encode(array('status' => 'error'));
    }
    
    exit();
}

function fsfwc_do_refund_order($order_id, $refunded_items)
{
    $wc_order = wc_get_order($order_id);
    
    $refund_args = array(
        'order_id' => $order_id
    );
    
    $refund_total = 0.0;
    
    $line_items = array();
    $restock_items = array();
    
    foreach ( $refunded_items as $refunded_item )
    {
        $refund_total += (double)$refunded_item['refund_total'];
        
        if ( (int)$refunded_item['restock_qty'] > 0 )
        {
            $restock_items[(string)$refunded_item['oipid']] = $refunded_item['restock_qty'];
        }
        
        $line_item = array(
            'qty' => $refunded_item['qty'],
            'refund_total' => $refunded_item['refund_total']
        );
        
        $line_items[$refunded_item['oiid']] = $line_item;
        
        unset($line_item);
    }
    
    if ( $wc_order->get_total() == $refund_total )
    {
        $wc_order->update_status('refunded', "", false);
    }
    else
    {
        $refund_args['amount'] = $refund_total;
        $refund_args['line_items'] = $line_items;
        
        $refund = wc_create_refund($refund_args);
    }
    
    foreach ( $restock_items as $product_id => $quantity )
    {
        $wc_product = wc_get_product($product_id);
        
        wc_update_product_stock($wc_product, $quantity, 'increase');
        
        unset($wc_product);
    }
    
    return $wc_order;
}

/**
 * Refunds items of an order and restocks specified quantities
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_refund_order($args)
{
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['status'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    $order_id = $args[2];
    $refunded_items = json_decode(stripslashes($args[3]), true);
    
    $wc_order = fsfwc_do_refund_order($order_id, $refunded_items);
    
    echo '{"status":"success","order":';
    
    fsfwc_do_output_single_order($wc_order);
    
    echo "}";
    
    exit();
}

/**
 * Creates a new customer or updates the customer's details if they exist
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_create_update_customer($args)
{
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['status'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    $customer_details = json_decode(stripslashes($args[2]), true);
    
    $customer_id = $customer_details['cid'];
    
    if ( $customer_id == '' )
    {
        // New customer
        $args = array(
            'search' => $customer_details['ce'],
            'search_columns' => array('user_email')
        );
        
        $query = new WP_User_Query($args);
        
        if ( !empty($query->results) )
        {
            echo '{"status":"error","message":"Email exists"}';
        
            exit();
        }
    }
    
    $customer_fields = array(
        'cbfn' => 'billing_first_name',
        'cbln' => 'billing_last_name',
        'cbco' => 'billing_company',
        'cba1' => 'billing_address_1',
        'cba2' => 'billing_address_2',
        'cbc' => 'billing_city',
        'cbpo' => 'billing_postcode',
        'cbcu' => 'billing_country',
        'cbs' => 'billing_state',
        'cbph' => 'billing_phone',
        'cbe' => 'billing_email',
        'csfn' => 'shipping_first_name',
        'csln' => 'shipping_last_name',
        'csco' => 'shipping_company',
        'csa1' => 'shipping_address_1',
        'csa2' => 'shipping_address_2',
        'csc' => 'shipping_city',
        'cspo' => 'shipping_postcode',
        'cscu' => 'shipping_country',
        'css' => 'shipping_state'
    );
    
    if ( $customer_id == '' )
    {
        // New customer
        $random_password = wp_generate_password( 12, false );
        $customer_id = wp_create_user( $customer_details['ce'], $random_password, $customer_details['ce'] );
    }
    
    $customer_id = wp_update_user(array(
        'ID' => $customer_id,
        'user_email' => $customer_details['ce'],
        'first_name' => $customer_details['cfn'],
        'last_name' => $customer_details['cln'],
        'role' => 'customer'
    ));

    if ( is_wp_error($customer_id) )
    {
        echo '{"status":"error","message":"Unknown"}';
    
        exit();
    }
    else
    {
        foreach ( $customer_fields as $key => $meta_key )
        {
            update_user_meta($customer_id, $meta_key, $customer_details[$key]);
        }
        
        echo '{"status":"success","cid":"' . (string)$customer_id . '"}';
    
        exit();
    }
}

/**
 * Gets the discount of a given coupon code for the current cart
 * 
 * @global object $wp_xmlrpc_server
 * @param type $args
 */
function fsfwc_get_coupon_code_discounts($args)
{
    error_reporting(E_ALL);

    ini_set('display_errors', '1');
    
    global $wp_xmlrpc_server;
    
    $wp_xmlrpc_server->escape($args);
    
    $username = $args[0];
    $password = $args[1];
    $user = '';

    if ( !$user = $wp_xmlrpc_server->login($username, $password) )
    {
        $output['message'] = false;
        
        echo json_encode($output);
        
        exit();
    }
    else
    {
        $output['message'] = true;
    }
    
    if ( !fsfwc_checkroles($user) )
    {
        $output['status'] = false;
        $output['invalid_user'] = "1";
        
        echo json_encode($output);
        
        exit();
    }
    
    global $woocommerce;
    
    $coupons = json_decode(stripslashes($args[2]), true);
    $order_items = json_decode(stripslashes($args[3]), true);
    
    date_default_timezone_set(fsfwc_get_timezone());
    
    $order = wc_create_order();
    
    // Order date
    wp_update_post(array(
        'ID' => $order->id,
        'post_date' => date('Y-m-d H:i:s', time())
    ));
    
    // Order items
    foreach ( $order_items as $order_item )
    {
        $line_total_excl = $order_item['oilst'];
        
        $product_args = array(
            'totals' => array(
                'subtotal' => $line_total_excl,
                'total' => $line_total_excl
            )
        );
        
        $wc_product = wc_get_product($order_item['pid']);
        
        $order->add_product($wc_product, $order_item['oiq'], $product_args);
        
        $product_args = null;
        
        unset($product_args);
    }
    
    $order->calculate_totals(); 
    
    if ( !empty($coupons) )
    {
        foreach ( $coupons as $coupon )
        {
            $coupon_result = $order->apply_coupon(new WC_Coupon($coupon));
            
            if ( is_wp_error($coupon_result) ) {
                $order->delete(true);
            
                $output['status'] = 'error';
                $output['message'] = html_entity_decode(strip_tags($coupon_result->get_error_message()));
                
                echo json_encode($output);
        
                exit();
            }
        }
    }
    
    $output['status'] = 'success';
    $output['discounts'] = array();
    
    if ( $coupons = $order->get_items('coupon') )
    {
        foreach ( $coupons as $coupon )
        {
            $output['discounts'][] = array(
                'coupon' => $coupon->get_code(),
                'discount' => $coupon->get_discount(),
                'discount_tax' => $coupon->get_discount_tax()
            );
        }
    }
    
    $order->delete(true);
    
    echo json_encode($output);
    
    exit();
}

function fsfwc_new_xmlrpc_methods($methods)
{
    $methods['fsfwc.test_access'] = 'fsfwc_test_access';
    $methods['fsfwc.connect_data_fetch'] = 'fsfwc_connect_data_fetch';
    $methods['fsfwc.update_product'] = 'fsfwc_update_product';
    $methods['fsfwc.create_order'] = 'fsfwc_create_order';
    $methods['fsfwc.sync_offline_changes'] = 'fsfwc_sync_offline_changes';
    $methods['fsfwc.cancel_order'] = 'fsfwc_cancel_order';
    $methods['fsfwc.refund_order'] = 'fsfwc_refund_order';
    $methods['fsfwc.create_update_customer'] = 'fsfwc_create_update_customer';
    $methods['fsfwc.get_coupon_code_discounts'] = 'fsfwc_get_coupon_code_discounts';
    
    return $methods;
}

add_filter('xmlrpc_methods', 'fsfwc_new_xmlrpc_methods');

function fsfwc_checkroles($user) {

    if(user_can($user, 'publish_foosales')) {

        return true;

    } else {
        
        return false;
        
    }

}

?>