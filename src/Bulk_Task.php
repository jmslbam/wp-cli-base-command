<?php

namespace JMSLBAM\WP_CLI;

trait Bulk_Task {
    
    /**
     * Loop through all your posts without it making you site crash.
     *
     * Prevents you from doing a 'posts_per_page' => '-1'.
     *
     * See: https://docs.wpvip.com/how-tos/write-custom-wp-cli-commands/wp-cli-commands-on-vip/#h-comment-and-provide-verbose-output
     *
     * @var array $query_args Arguments for WP_Query
     * @var callable $callback The function to be called for each Post ID that comes from the WP_Query
     * @var array $query_args Arguments to passed to the $callback function
     */
    public function loop_posts( array $query_args = [], $callback = false, array $callback_args = []  ) {

        if( ! \is_callable( $callback ) ) {
            error_log( 'Loop: $callback not callable' );
            return;
        }

        // Turn --post__in=1337,187 into an Array
        $query_args = $this->process_csv_arguments_to_arrays( $query_args );

        /**
		 * WP_Query: Only query those which have a combination of this taxonomy & term selected.
		 */
		$query_args = $this->parse_assoc_args( $query_args );

        // Set base value of these variables that are also being used outside of the while loop
        $offset = $total = 0;

        do {

            /**
             * Keeps track of the post count because we can't overwrite $query->post_count.
             * I used that variable before in the while() check, but now use $count.
             */
            $count = 0;

            $defaults = [
                'post_type'              => [ 'post' ],
                'post_status'            => [ 'publish' ],
                'posts_per_page'         => 500,
                'paged'                  => 0,
                'fields'                 => 'ids',
                'update_post_term_cache' => false, // useful when taxonomy terms will not be utilized.
		        'update_post_meta_cache' => false, // useful when post meta will not be utilized.
			];

            $query_args = \wp_parse_args( $query_args, $defaults );

            /**
             * Fixed values
             */

            // Otherwise these will be appened to the query
            $query_args['ignore_sticky_posts'] = true;

            // In rare situations (possibly WP-CLI commands)
            $query_args['cache_results'] = false;

            // Don't want a random `pre_get_posts` get in our way
            $query_args['suppress_filters'] = true; 

            // Force to false so we can skip SQL_CALC_FOUND_ROWS for performance (no pagination).
            $query_args['no_found_rows'] = false;

            // When adding 'nopaging' the code breaks... don't know why, haven't investigated it yet.
            unset( $query_args['nopaging' ] );

            // Base value is 0 (zero) and is upped with the 'posts_per_page' at the end of this function.
            $query_args['offset'] = $offset;

            // Get them all, you probaly have a pretty good reason to be using these.
            if( isset( $query_args['p'] ) || isset( $query_args['post__in'] ) || isset( $query_args['post__not_in'] ) ) {
                $query_args['posts_per_page'] = -1;
            }

            // Get the posts
            $query = new \WP_Query( $query_args );

            foreach ( $query->posts as $post_id ) {
                // Always pass the post_id and the query_args, which are the assoc_args, as an extra. You never know.
                $result = call_user_func_array( $callback, [ $post_id, $query_args ] );
                $count++;
                $total++;
            }

            /**
             * 'offset' and 'posts_per_page' are being dropped when 'p' or 'post__in' are being used.
             * So it will run without a limit and $query->post_count will always be higher then 0 (false) or 0 when it didn't find anything offcourse.
             *
             * But it is helpfull if you just want to set 1 post ;)
             */
            if( isset( $query_args['p'] ) || isset( $query_args['post__in'] ) || isset( $query_args['post__not_in'] ) ) {

                \WP_CLI::log( \WP_CLI::colorize('%8Using p, post__in or post__not_in results quering ALL posts, which are not batches by "posts_per_page" and therefor not taking advantage of the goal of this loop.%n') );
                $count = 0;
            }

            // Get a slice of all posts, which result in SQL "LIMIT 0,100", "LIMIT 100, 100", "LIMIT 200, 100" etc. And therefor creating an alternative for $query->have_posts() which can't use because we set 'no_found_rows' to TRUE.
            $offset = $offset + $query_args['posts_per_page'];

            // Contain memory leaks
			if ( method_exists( $this, 'free_up_memory' ) ) {
				$this->free_up_memory();
			}

        } while ( $count > 0 );

        \WP_CLI::line( "{$total} items processed." );
    }

    /**
	 * Transforms arguments with '__' from CSV into expected arrays
	 *
     * Added the check if value is a string, because if it's already an Array, this results in an error.
     *
     * Example --post__in=1337,187 into an array [1337,187]
     *
	 * @param array $assoc_args
	 * @return array
     *
     * @props https://github.com/wp-cli/entity-command/blob/master/src/WP_CLI/CommandWithDBObject.php#L99
	 */
	protected function process_csv_arguments_to_arrays( $assoc_args ): array {

		foreach ( $assoc_args as $key => $value ) {
			if ( str_contains( $key, '__' ) && is_string( $value ) ) {
				$assoc_args[ $key ] = explode( ',', $value );
			}
		}

		return $assoc_args;
	}

    /**
     * Helpers function to parse --taxonomy=tag && --term=snowboarding
     *
     * @param array $assoc_args
     * @return array
     */
    protected function parse_assoc_args( $assoc_args ): array {

		if( ! isset( $assoc_args['taxonomy'] ) ||  ! isset( $assoc_args['terms'] ) ) {
            return $assoc_args;
        }

        $tax_terms = explode( ',', $assoc_args['terms'] );

		if( is_array( $tax_terms ) && ! empty( $tax_terms ) ) {

            $only_integers = $this->array_contains_only_numbers( $tax_terms );

			$assoc_args['tax_query'] = [
                'taxonomy' => $assoc_args['taxonomy'],
                'field'    => ( ( $only_integers ) ? 'id' : 'slug' ),
                'terms'    => array_values($tax_terms),
			];
		}

        unset( $assoc_args['taxonomy'], $assoc_args['terms'] );

		return $assoc_args;
	}

    protected function array_contains_only_numbers( array $array ): bool {

        foreach( $array as $value ) {
            if( ! is_numeric( $value ) ) {
                return false;
            }
        }

        return true;
    }
}