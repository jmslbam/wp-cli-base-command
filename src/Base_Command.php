<?php

namespace JMSLBAM\WP_CLI;

use WP_CLI_Command;

/**
 * Base command class for WP-CLI Commands.
 */
class Base_Command extends \WP_CLI_Command {

    /**
     * Mimimize the load while importing.
     *
     * If you don't want this maxed out, then overwrite it in your own Command.
     */
    public function __construct() {

        // Ensure only the minimum of extra actions are fired.
        if ( ! defined( 'WP_IMPORTING' ) ) {
            define( 'WP_IMPORTING', true );
        }

        // This can cut down significantly on memory usage. Thank you 10up
        if ( ! defined( 'WP_POST_REVISIONS' ) ) {
            define( 'WP_POST_REVISIONS', 0 );
        }
    }

    /**
     * Disable hooks that you don't want to run while running inserts of updates.
     * Run these hooks from their own individuel commands.
     */
    protected function disable_hooks(): void {

        // SearchWP: Stop the SearchWP indexer process
        add_filter( 'searchwp\index\process\enabled', '__return_false' );

        // FacetWP (post version 3.8)
        add_filter( 'facetwp_indexer_is_enabled', '__return_false' );

        // ElasticPress: Disable indexes to nothing will be synced
        add_filter( 'ep_indexable_sites','__return_empty_array' );

        // Woocommerce
        add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
    }

    /**
     * Disable Term and Comment counting so that they are not all recounted after every term or post operation.
     *
     * Run "wp term recount <taxonomy>..." afterwards.
     */
    protected function start_bulk_operation(): void {
        wp_defer_term_counting( true );
        wp_defer_comment_counting( true );
    }

    /**
     * Re-enable Term and Comment counting and trigger a term counting operation to update all term counts
     */
    protected function end_bulk_operation(): void {
        wp_defer_term_counting( false );
        wp_defer_comment_counting( false );
        $this->free_up_memory();
    }

    /**
	 *	Resets some values to reduce memory footprint.
     */
    protected function free_up_memory(): void {
        $this->clear_db_query_log();
        $this->clear_actions_log();
        $this->clear_local_object_cache();
		$this->clear_get_term_metadata();
    }

    /**
     * Reset the WordPress DB query log
     */
    protected function clear_db_query_log(): void {
        global $wpdb;

        $wpdb->queries = [];
    }

    /**
     * Reset the WordPress Actions log
     */
    protected function clear_actions_log(): void {
        global $wp_actions;

        $wp_actions = []; //phpcs:ignore
    }

    /**
     * Reset WordPress internal object caches.
     *
     * This only cleans the local cache in WP_Object_Cache, without
     * affecting memcache.
     *
     * In long-running scripts, the internal caches on `$wp_object_cache` and `$wpdb`
     * can grow to consume gigabytes of memory. Periodically calling this utility
     * can help with memory management.
     *
     * @return void
     *
     * @props https://github.com/wp-cli/wp-cli/blob/master/php/utils-wp.php, https://github.com/Automattic/vip-go-mu-plugins/blob/develop/vip-helpers/vip-wp-cli.php and https://github.com/10up/ElasticPress/blob/0a7feb5c96d4dd5f5a416731742b346fb1880f8c/includes/classes/IndexHelper.php#L1200
     *
     * But beware, because VIP remove the clearing of the memcached, probaly because that's just to heavy?
     */
    protected function clear_local_object_cache(): void {
        global $wp_object_cache;

		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		} else {
			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}
		}

        if ( ! is_object( $wp_object_cache ) ) {
            return;
        }

        // The following are Memcached (Redux) plugin specific (see https://core.trac.wordpress.org/ticket/31463).
        if ( isset( $wp_object_cache->group_ops ) ) {
            $wp_object_cache->group_ops = [];
        }

        if ( isset( $wp_object_cache->stats ) ) {
            $wp_object_cache->stats = [];
        }

        if ( isset( $wp_object_cache->memcache_debug ) ) {
            $wp_object_cache->memcache_debug = [];
        }

        // Used by `WP_Object_Cache` also.
        if ( isset( $wp_object_cache->cache ) ) {
            $wp_object_cache->cache = [];
        }

        // Make sure this is a public property, before trying to clear it.
		try {
			$cache_property = new \ReflectionProperty( $wp_object_cache, 'cache' );
			if ( $cache_property->isPublic() ) {
				$wp_object_cache->cache = [];
			}
			unset( $cache_property );
		} catch ( \ReflectionException $e ) {
			// No need to catch.
		}

		if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
			call_user_func( [ $wp_object_cache, '__remoteset' ] );
		}
    }

	/**
	 * It's high memory consuming as WP_Query instance holds all query results inside itself
	 * and in theory $wp_filter will not stop growing until Out Of Memory exception occurs.
     * 
     * @props https://github.com/10up/ElasticPress/blob/0a7feb5c96d4dd5f5a416731742b346fb1880f8c/includes/classes/IndexHelper.php#L1249
	 *
	 * @return void
	 */
	protected function clear_get_term_metadata() {
		remove_filter( 'get_term_metadata', [ wp_metadata_lazyloader(), 'lazyload_term_meta' ] );
	}
}