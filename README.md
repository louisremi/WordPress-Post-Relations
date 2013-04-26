WordPress Post Relations
========================

This library allows to create relations between post types in WordPress. It uses native WordPress features (taxonomies) and UI, so the code is very short (~200 well-spaced LOC), easy to understand and extend.  
Bonus feature: Display radio-buttons in any taxonomy ( 1 to 1 relations! ).

Short example
-------------

```php
/* This website is going to present wines and castles.
 * Relations are going to be created *from* a wine *to* the castle that produces it.
 */
include 'WordPress-Post-Relations/init.php';

add_action( 'init', function() {
	$from_type = 'wines';
	$to_type = 'castles';
	$relation_name = 'wines-to-castles';

	// Register custom post types as usual
	// The only prerequisite is that the "from type" should support page-attributes
	register_post_type( $from_type, array(
		'supports' => array(
			'page-attributes'
		)
	));

	register_post_type( $to_type );

	// Register the relation
	register_post_relation( $from_type, $to_type, $relation_name, array(
		'radio' => true
	));

	// That's it for the main function and option.
	// Now go create some castles, then edit a wine to see the magic operate.
});
```

Installation & Usage
--------------------

1. Place `WordPress-Post-Relations` directory inside of your current theme.
2. Include `init.php` at the beginning of functions.php

This plugin has been tested with WordPress 3.5

When using WPML, translations should be enabled for each relation individually: navigate to "WPML > translation options" and enable translations in the "Custom taxonomies" box.

Documentation
-------------

**register_post_relation( $from_type, $to_type, $relation_name, $args )**

uses [register_taxonomy](http://codex.wordpress.org/Function_Reference/register_taxonomy)

- `$from_type` : (***string***) The name of the post type that will display the relation meta-box
- `$to_type` : (***string***) The name of the related post type
- `$relation_name` : (***string***) The name of the relation (will be used as the taxonomy name)
- `$args` : can be used to pass additional options to `register_taxonomy`. Setting `'radio'` to `true` will display radio buttons for this taxonomy.

**get_related_posts( $post_ID, $relation_name, $args )**

uses [get_posts](http://codex.wordpress.org/Function_Reference/get_posts)

- `$post_id` : (***int***) The ID of a post
- `$relation_name` : (***string***) The name of the relation
- `$args` : can be used to pass additional options to `get_posts` (specifying the `'post_type'` that should be returned will save one DB query)

**query_related_posts( $post_ID, $relation_name, $args )**

uses [query_posts](http://codex.wordpress.org/Function_Reference/query_posts)

- `$post_id` : (***int***) The ID of a post
- `$relation_name` : (***string***) The name of the relation
- `$args` : can be used to pass additional options to `query_posts` (specifying the `'post_type'` that should be returned will save one DB query)

Credits & License
-----------------

GPLv3 licensed by [@louis_remi](http://twitter.com/louis_remi)

Are you using this in a paid work?  
Send me music http://www.amazon.co.uk/wishlist/HNTU0468LQON