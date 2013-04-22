WordPress-Post-Relations
========================

This library allows to create relations between post types in WordPress. It uses native WordPress features (taxonomies) and UI, so the code is very short (~200 well-spaced lines), easy to understand and extend.  
Bonus feature: relations can be "1 to 1" and that behavior can be applied to any hierarchical taxonomy.

Short example
-------------

```php
/* This website is going to present wines and the castles.
 * Relations are going to be created *from* a wine *to* the castle that produces it.
 */
add_action( 'init', 'create_types_and_relations' );
function create_types_and_relations() {
	$from_type = 'wines';
	$to_type = 'castles';

	// Register custom post types as usual
	// The only prerequisite is that the "from type" should support page-attributes
	register_post_type( $from_type, array(
		'supports' => array(
			'page-attributes'
		)
	));

	register_post_type( $to_type );

	// Register the relation
	register_post_relation( $from_type, $to_type, array(
		'1_to_1' => true
	));

	// That's it for the main function and option.
	// Now go create some castles, then try to create a wine to see the magic operate.
}
```

Installation & Usage
--------------------

1. Place `WordPress-Post-Relations` directory inside of your (activated) theme. E.g. inside `/themes/twentytwelve/lib/WordPress-Post-Relations/`.
2. Include `init.php` at the beginning of the 'init' WordPress hook.

Credits & License
-----------------

GPLv3 licensed by [@louis_remi](http://twitter.com/louis_remi)

Are you using this in a paid work?  
Send me music http://www.amazon.co.uk/wishlist/HNTU0468LQON