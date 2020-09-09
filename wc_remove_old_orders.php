<?php
/**
 * Plugin Name: Remove Old Orders
 * Plugin URI:  https://github.com/Maximoo/remove_old_orders
 * Description: Orders are not a problem in the DB, you just have to hide the oldest ones in the administrator section.
 * Version:     1.0
 * Author:      Maximo_o
 * Author URI:  https://github.com/Maximoo
 * Donate link: https://github.com/Maximoo
 * License:     GPLv3
 * Text Domain: remove_old_orders
 *
 * @link https://github.com/Maximoo
 *
 * @package RemoveOldOrders
 * @version 1.0
 */

/**
 * Copyright (c) 2020 Maximo_o (email : deluzmax@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class RemoveOldOrders {

  private $year = 2019;

  protected static $single_instance = null;
  public static function get_instance() {
    if ( null === self::$single_instance ) {
      self::$single_instance = new self();
    }
    return self::$single_instance;
  }

  public function hooks() {
    add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
    add_filter( 'months_dropdown_results', array( $this, 'months_dropdown_results' ), 10, 2 );
    add_filter( 'wp_count_posts', array( $this, 'wp_count_posts' ), 100, 3 );
  }

  public function pre_get_posts( $query ) {
    if ( is_admin() && $query->get('post_type') == 'shop_order' ) {
      $query->set( 'date_query', array(
        'date_query' => array(
          array(
            'after' => $this->year . '-01-01 00:00:00'
          )
        )
      )); 
    }
  }

  public function wp_count_posts( $counts, $type, $perm ) {
    if($type = 'shop_order'){
      global $wpdb;

      //TODO: Cache Counts

      $query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
      if ( 'readable' === $perm && is_user_logged_in() ) {
        $post_type_object = get_post_type_object( $type );
        if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
          $query .= $wpdb->prepare(
              " AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
              get_current_user_id()
          );
        }
      }
      $query .= ' AND post_date >= \''.$this->year.'-01-01 00:00:00\' GROUP BY post_status';
   
      $results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
      $counts  = array_fill_keys( get_post_stati(), 0 );   
      foreach ( $results as $row ) {
          $counts[ $row['post_status'] ] = $row['num_posts'];
      }
      $counts = (object) $counts;
      wp_cache_set( _count_posts_cache_key( $type, $perm ), $counts, 'counts' );
    }
    return $counts;
  }

  public function months_dropdown_results( $months, $post_type ) {
    if ( $post_type == 'shop_order' ) {
      $_months = array();
      for ($i=0; $i < count($months); $i++) { 
        if((int) $months[$i]->year >= $this->year ){
          $_months[] = $months[$i];
        }
      }
      $months = $_months;
    }
    return $months;
  }

  public function _activate() {
    wp_cache_delete(_count_posts_cache_key( 'shop_order', 'readable' ),'counts');
  }
  public function _deactivate() {
    wp_cache_delete(_count_posts_cache_key( 'shop_order', 'readable' ),'counts');
  }
};

function remove_old_orders() {
  return RemoveOldOrders::get_instance();
}

add_action( 'plugins_loaded', array( remove_old_orders(), 'hooks' ) );
register_activation_hook( __FILE__, array( remove_old_orders(), '_activate' ) );
register_deactivation_hook( __FILE__, array( remove_old_orders(), '_deactivate' ) );