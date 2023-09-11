# Usage

1. Install via `composer require jmslbam/wp-cli-base-command`.

2. Extend your own command:

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