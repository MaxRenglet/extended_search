# Extended Search

## How to use ?

```php
<?php

use RMAX_\WP_Extended_Search;


$args = array(
    'orderby'          => 'date', // Date or title (for now)
    'order'            => 'DESC', // ASC or DESC
    'post_type'        => "any", // Any or any of the CPT array('post', 'page')
    'post_status'      => 'any', // Any or any of the Post Status ('publish', 'draft')
    'posts_per_page'   => 10, // Number of posts per page
    'paged'            => 1, // Page you want to query (default 1)
    'meta_query'       => array( // Filter post by postmeta(s)
        'relation' => 'OR', // Required if using meta_query
        array(
            'key'     => 'meta_key', // What key to search
            'value'   => 'meta_value', // What value to search
            'compare' => '=' // Comparation (=, IN)
        ),
    ),
    's'               => 'search_string', // Search string / Required if using meta_to_search
    'meta_to_search'  => array('meta_key1', 'meta_key2'), // Meta_key to search in
    'tax_query'        => array( // Filter post by taxonomie(s)
        'relation' => 'OR', // Required if using tax_query
        array(
            'taxonomy' => 'category', // Taxonomy
            'terms'    => array(115) // Array of id of terms
        ),
    ),
);

$query = new WP_Extended_Search($args);

?>
```
