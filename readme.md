# Usage

Install via `composer require jmslbam/wp-cli-base-command`.

# Extend your own command:

```php
<?php

use \JMSLBAM\WP_CLI\Base_Command;

class Import extends Base_Command {

    function import( $args, $assoc_args ) {

        $this->start_bulk_operation();

        // Optional: Disable a bunch of pre-defined plugin actions
        $this->disable_hooks();

        // Optional: Call "free_up_memory" after importing X amount of posts
        $this->free_up_memory();

        // Finalize your command
        $this->end_bulk_operation();
    }
}
```

# Use Bulk task helper
Use the Bulk_Task to easily loop over all kind of CPT's and preform a task on it.

```php
<?php
namespace JMSLBAM;

use JMSLBAM\WP_CLI\Base_Command;
use JMSLBAM\WP_CLI\Bulk_Task;

class Test extends Base_Command {

	use Bulk_Task;

	function run( $args, $assoc_args ) {

		// $assoc_args['post_type'] = 'post';
		$result = $this->loop_posts( $assoc_args, [ $this, 'do_something' ] );
	}

	private function do_something( $post_id, $assoc_args = [] ) {

		$post = get_post( $post_id );

        	$post->post_title = $post->post_title . ' x';

		\WP_CLI::line($post_id . '. ' . $post->post_title . ' (' . $post->ID . ')' );

		\wp_update_post( $post ); // re-save post
	}
}
```

```php
if( defined('WP_CLI') ) {
	\WP_CLI::add_command( 'test', 'JMSLBAM\\Test' );
}
```

Example command output:

```bash
➜  wp test run --post_type=product
50. Heavy Duty Silk Gloves x (50)
49. Durable Rubber Bench x (49)
17. WordCamp x (17)
3 items processed.
```

Other posiblities:

```bash
wp test run --post_type=accommodation --taxonomy=region --term=france
```

Or any other `WP_Query` argument
