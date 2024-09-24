<?php

/**
 * The basic functionality of the plugin.
 *
 * @link       http://www.nerdissimo.de
 * @since      1.0.0
 *
 * @package    Woo_Pfand
 * @subpackage Woo_Pfand/basic
 */

/**
 * @package    Woo_Pfand
 * @subpackage Woo_Pfand/basic
 * @author     Daniel Kay <daniel@nerdissimo.de>
 */
class Woo_Pfand_Basic {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $woo_pfand    The ID of this plugin.
	 */
	private $woo_pfand;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @var      string    $woo_pfand       The name of the plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct( $woo_pfand, $version ) {

        $this->woo_pfand = $woo_pfand;
        $this->version = $version;

        add_filter( 'woocommerce_get_price_suffix', array( $this, 'add_deposit_value_to_price_suffix' ), 10, 2 );

        add_filter( 'woocommerce_cart_item_price', array( $this, 'add_deposit_value_to_cart' ), 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'add_deposit_value_to_cart' ), 10, 3 );

        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_deposit_value_to_totals' ) );

	}

    /**
     * Adds deposit value to price displays
     * e.g. archive, content, single
     *
     * @since   1.0.0
     */
    function add_deposit_value_to_price_suffix( $price_display_suffix, $product ) {

        if( get_option( 'wc_deposit_hide_in_loop' ) == 'yes' )
            return $price_display_suffix;

        if( ! $this->display_deposit() )
            return $price_display_suffix;

        $dep_total = $this->get_deposit( $product->id );

        if( ! empty( $dep_total ) ) {

            $price_display_suffix = preg_replace( '/<\/small>/', '', $price_display_suffix, 1 ); // removes </small>

            $price_display_suffix .= '<br />';
			$price_display_suffix .= '<span class="small-deposit-important">';
            $price_display_suffix .= sprintf( __( 'plus deposit of %s', $this->woo_pfand ), wc_price( $dep_total ) );
			$price_display_suffix .= '</span>';

            $price_display_suffix .= '</small>';

        }

        return $price_display_suffix;
    }

    /**
     * Adds deposit value to item price displays in cart and checkout
     *
     * @since   1.0.0
     */
    function add_deposit_value_to_cart( $product_price, $values, $cart_item_key ) {

        if( get_option( 'wc_deposit_hide_in_cart' ) == 'yes' )
            return $product_price; 

        if( ! $this->display_deposit() )
            return $product_price;

        $qty = 1;
        if( current_filter() == 'woocommerce_cart_item_subtotal' )
            $qty = $values['quantity'];

        $dep_total = $this->get_deposit( $values['product_id'], $qty );

        if( ! empty( $dep_total ) )
            return $product_price . '<br /><small class="deposit_label">' .  sprintf( __( 'plus deposit of %s', $this->woo_pfand ), wc_price( $dep_total ) ) . '</small>';
 
        return $product_price;
    }

    /**
     * Adds deposit value to totals
     */
    function add_deposit_value_to_totals() {

        if( ! $this->display_deposit() )
            return;

        global $woocommerce;

        $dep_total = 0;
        $tax = $tax_class = false;
        $product_id = 2992; // hardcoded product id for garantie sgr
        $dep_quantity_total = 0;

        // variables to track if the product is found in the cart
        $found = false;
        $cart_item_key_found = '';

        foreach( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $dep_total = $dep_total + $this->get_deposit( $cart_item['product_id'], $cart_item['quantity'] );
            $current_dep = $this->get_deposit( $cart_item['product_id'], $cart_item['quantity'] );

            if( $current_dep > 0 && $cart_item['product_id'] != $product_id ) {
                $dep_quantity_total = $dep_quantity_total + $cart_item['quantity'];
            }

            if( !$found && $cart_item['product_id'] == $product_id ) {
                $found = true;
                $cart_item_key_found = $cart_item_key;
            }

//             $deposit_item = $this->get_deposit( $cart_item['product_id'], $cart_item['quantity'] );
//             if ( $deposit_item > 0 ) {
//                 // concatenate 'Garanție SGR' with the product name
//                 $woocommerce->cart->add_fee( 'Garanție SGR ' . $cart_item['quantity'] . 'x ' . $cart_item['data']->get_name(), $deposit_item, $tax, $tax_class );
//             }
        }

//         $tax = apply_filters( 'add_deposit_value_to_totals', $tax );
//         $dep_total = apply_filters( 'dep_total_before_add_fee', $dep_total );
//         $tax_class = apply_filters( 'tax_class_before_add_fee', $tax_class );

        if( $dep_quantity_total > 0 ) {
            if (!$found) {
//              echo '<pre>';
//              print_r($found);
//              echo '<br/>';
//              print_r($cart_item_key_found);
//              echo '</pre>';
//              die('End of life');
                $woocommerce->cart->add_to_cart( $product_id, $dep_quantity_total );
            } else {
//              echo '<pre>';
//              print_r($found);
//              echo '<br/>';
//              print_r($cart_item_key_found);
//              echo '<br/>';
//              print_r($dep_quantity_total);
//              echo '</pre>';
//              die('End of life');
                $woocommerce->cart->set_quantity( $cart_item_key_found, $dep_quantity_total, false );
            }
        } elseif( $found && $dep_quantity_total == 0 ) {
            $woocommerce->cart->remove_cart_item( $cart_item_key_found );
        }

        return;

//         if( $dep_total > 0 )
//             $woocommerce->cart->add_fee( __( 'Deposit Total', $this->woo_pfand ), $dep_total, $tax, $tax_class );

    }

    /**
     * Function to determine whether deposit should be displayed
     *
     * @since   2.0.0
     */
    static function display_deposit() {

        $display = true;

        if( is_admin() ) 
            return false; 

        $display = apply_filters( 'display_deposit', $display );

        return $display;
    }

    /**
     * Function to calculate deposits
     *
     * @since   1.0.0
     */
    static function get_deposit( $id, $quantity = 1 ) {

        $dep_total = 0;
        $terms = get_the_terms( $id, 'product_deptype' );

        if( $terms && ! is_wp_error( $terms ) ) {
            $dep_total = 0;
            foreach( $terms as $term ) {
                $dep_total = $dep_total + $term->name;
            }
        }  

        if( $dep_total > 0 ) {
            $dep_total = $dep_total * $quantity;
            $dep_total = apply_filters( 'get_deposit', $dep_total );
            return $dep_total;
        }

        return false;
    }

}
