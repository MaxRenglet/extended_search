<?php

namespace RMAX_;

class WP_Extended_Search
{

    /**
     * Default value of the args used to create the query
     *
     * @var array
     */
    private $default = array(
        'posts_per_page'   => 10,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'post_type'        => 'post',
        'post_status'      => 'publish',
        's'                => '',
        'tax_query'        => array(),
        'meta_query'       => array(),
        'paged'            => '1',
        'meta_to_search'   => array()
    );


    /**
     * Args used to create the query
     *
     * @var [type]
     */
    private $args;

    private $prefix;
    public $posts;
    public $posts_found;
    public $posts_count;
    public $current_page;
    public $max_page;


    function __construct($args = [])
    {

        $this->args = array_merge($this->default, $args);

        // foreach ($this->args as $key => $arg) {
        //     call_user_func(array($this,$key.'_query'), $arg);
        // }


        $this->create_query();


        // $this->get_posts();
        // var_dump($this->posts);
        // die();
            // return $this->posts;
    }


    private function create_query()
    {

        global $wpdb;

        $this->prefix = $wpdb->prefix;

        $tax_query_inner_join = "";
        if ($this->args['tax_query']) {
            $tax_query_inner_join = "LEFT JOIN " . $this->prefix . "term_relationships ON (" . $this->prefix . "posts.ID = " . $this->prefix . "term_relationships.object_id)";
        }

        $meta_query_inner_join = "";
        if ($this->args['meta_query'] or $this->args['meta_to_search']) {
            $meta_query_inner_join = "INNER JOIN " . $this->prefix . "postmeta ON ( " . $this->prefix . "posts.ID = " . $this->prefix . "postmeta.post_id )";
        }


        $this->query = "
        SELECT DISTINCT  " . $this->prefix . "posts.*
        FROM " . $this->prefix . "posts " . $tax_query_inner_join . "  " . $meta_query_inner_join . " WHERE 1=1  

        " . $this->get_tax_query() . "
        " . $this->get_meta_query() . "

       " . $this->get_search_content() . "

        AND (( " . $this->get_post_type() . "
        AND (" . $this->get_post_status() . ")
        
        )) " . $this->get_orderby() . " " . $this->get_order() . " " . $this->get_limit();

        
        $this->posts_count = $this->get_posts_count();
        $this->max_page = $this->get_max_page();

 
        $this->posts = $wpdb->get_results($this->query);
        $this->posts_found = count( $this->posts);
        // die();
    }

    private function get_max_page(){

        $max_posts = intval($this->posts_count);
        $page_posts = intval($this->args['posts_per_page']);

        
        return intval(ceil($max_posts/$page_posts));

    }
    
    private function get_posts_count(){


        global $wpdb;

        $this->prefix = $wpdb->prefix;

        $tax_query_inner_join = "";
        if ($this->args['tax_query']) {
            $tax_query_inner_join = "LEFT JOIN " . $this->prefix . "term_relationships ON (" . $this->prefix . "posts.ID = " . $this->prefix . "term_relationships.object_id)";
        }

        $meta_query_inner_join = "";
        if ($this->args['meta_query'] or $this->args['meta_to_search']) {
            $meta_query_inner_join = "INNER JOIN " . $this->prefix . "postmeta ON ( " . $this->prefix . "posts.ID = " . $this->prefix . "postmeta.post_id )";
        }


        $query = "
        SELECT COUNT(*)
        FROM " . $this->prefix . "posts " . $tax_query_inner_join . "  " . $meta_query_inner_join . " WHERE 1=1  

        " . $this->get_tax_query() . "
        " . $this->get_meta_query() . "

       " . $this->get_search_content() . "

        AND (( " . $this->get_post_type() . "
        AND (" . $this->get_post_status() . ")
        
        )) " . $this->get_orderby() . " " . $this->get_order();

        
        $count = ($wpdb->get_results($query, "ARRAY_A")[0]);
        $count = intval(reset($count));

        return $count;
    

    }


    /**
     * 
     * Function to get the Search string with or without meta_key to the SQL request
     *
     * @return void
     */
    private function get_search_content()
    {

        $meta_search = "";
        if($this->args['meta_to_search'] && $this->args['s']){
            $meta_search = '
            OR (';

            foreach ($this->args['meta_to_search'] as $key => $meta_key) {
                if($key > 0){
                    $meta_search .= " OR ( (" . $this->prefix . "postmeta.meta_key = '".$meta_key."')
                    AND (" . $this->prefix . "postmeta.meta_value  LIKE LOWER('%".$this->args['s']."%')))";
                } else {
                    $meta_search .= " ( (" . $this->prefix . "postmeta.meta_key = '".$meta_key."')
                    AND (" . $this->prefix . "postmeta.meta_value  LIKE LOWER('%".$this->args['s']."%')))";
                }
            }

            // $meta_search .= ")"; 
        }
        
        $content_search = "";
        if($this->args['s']){
            $content_search = "AND((( LOWER(" . $this->prefix . "posts.post_title) LIKE LOWER('%".$this->args['s']."%') )
            OR (LOWER(" . $this->prefix . "posts.post_excerpt) LIKE LOWER('%".$this->args['s']."%') )
            OR (LOWER(" . $this->prefix . "posts.post_content) LIKE LOWER('%".$this->args['s']."%') ))".$meta_search."))";
        }

       

       

        return $content_search;
    }
    /**
     * 
     * Function to get Meta Query filter to SQL
     *
     * @return void
     */
    private function get_meta_query()
    {

        $meta_query_sql = [];
        $meta_query_str = "";
        $meta_query = [];

        if (isset($this->args['meta_query'])) {

            $meta_query = $this->args['meta_query'];

            if ($meta_query) {

                if (gettype($meta_query) === 'array') {

                    $relation = 'OR';

                    if (isset($meta_query['relation'])) {
                        $relation =  $meta_query['relation'];
                        unset($meta_query['relation']);
                    }

                    foreach ($meta_query as $key => $query) {
                        //var_dump($query);
                        if ($query['key'] && $query['value'] && $query['compare']) {

                            if (strtolower($query['compare']) === 'in') {
                                $query['value'] = explode(' ', $query['value']);
                                $query['value'] = "('" . implode("','", $query['value']) . "')";
                            } else {
                                $query['value'] = "'" . $query['value'] . "'";
                            }

                            // var_dump($query['value']);
                            array_push($meta_query_sql, "(  " . $this->prefix . "postmeta.meta_key = '" . $query['key'] . "' AND  " . $this->prefix . "postmeta.meta_value " . $query['compare'] . " " . $query['value'] . " )");
                        }
                    }


                    $meta_query_sql = implode(" " . $relation . " ", $meta_query_sql);
                }
            }
        }

        if ($meta_query_sql) {
            return 'AND ( ' . $meta_query_sql . ' )';
        } else {
            return '';
        }


        return "AND ( ( wp_postmeta.meta_key = '_my_custom_key' AND wp_postmeta.meta_value = 'Value I am looking for' ) OR ( wp_postmeta.meta_key = 'test' AND wp_postmeta.meta_value = 'Value' ) )";
    }

    /**
     * 
     * Function to get Tax Query to sql request
     *
     * @return void
     */
    private function get_tax_query()
    {

        $tax_query_sql = [];
        $tax_query = [];

        if (isset($this->args['tax_query'])) {

            $tax_query = $this->args['tax_query'];

            if ($tax_query) {

                if (gettype($tax_query) === 'array') {

                    $relation = 'OR';

                    if (isset($tax_query['relation'])) {
                        $relation =  $tax_query['relation'];
                        unset($tax_query['relation']);
                    }

                    foreach ($tax_query as $key => $query) {
                        if ($query['terms']) {

                            if (gettype($query['terms']) === 'array') {
                                $query['terms'] = implode(',', $query['terms']);
                            }
                            array_push($tax_query_sql, "wp_term_relationships.term_taxonomy_id IN (" . $query['terms'] . ")");
                        }
                    }


                    $tax_query_sql = implode(" " . $relation . " ", $tax_query_sql);
                }
            }
        }

        if ($tax_query_sql) {
            return 'AND ( ' . $tax_query_sql . ' )';
        } else {
            return '';
        }
        // return 'AND ( ' . $tax_query_sql . ' )';
    }

    /**
     * Function to retrieve Order By and pass it to the SQL
     *
     * @return void
     */
    private function get_orderby()
    {
        if ($this->args['orderby'] === "date") {
            $orderby = 'post_date';
        } else if ($this->args['orderby'] === "title") {
            $orderby = 'post_title';
        }
        $order = "ORDER BY " . $this->prefix . "posts." . $orderby;
        return $order;
    }

    /**
     * 
     * Function get the Order and pass it to the SQL
     *
     * @return void
     */
    private function get_order()
    {
        if (strtolower($this->args['order']) === "asc") {
            $order = 'ASC';
        } else if (strtolower($this->args['order']) === "desc") {
            $order = 'DESC';
        }
        return $order;
    }

    /**
     * 
     * Function to retrieve Post Status and return the SQL
     *
     * @return void
     */
    private function get_post_status()
    {

        $status = get_post_statuses();

        if ($this->args['post_status'] === 'any') {
            $post_status = array_keys(get_post_statuses());
        } else {
            $post_status = $this->args['post_status'];
        }

        if (gettype($post_status) === 'array') {
            $q = "" . $this->prefix . "posts.post_status IN ('" . implode("','", $post_status) . "')";
        } else {
            $q = "" . $this->prefix . "posts.post_status = '" . $post_status . "'";
        }

        return $q;
    }
    /**
     * 
     * Function to retrieve Post Type and return the SQL
     *
     * @return void
     */
    private function get_post_type()
    {
        if ($this->args['post_type'] === 'any') {
            $post_type = get_post_types(array('exclude_from_search' => false));
        } else {
            $post_type = $this->args['post_type'];
        }


        $q = "";

        if (gettype($post_type) === 'array') {
            $q = "" . $this->prefix . "posts.post_type IN ('" . implode("','", $post_type) . "')";
        } else {
            $q = "" . $this->prefix . "posts.post_type = '" . $post_type . "'";
        }



        return $q;
    }

    /**
     * 
     * Function to get the Limit and return the SQL
     *
     * @return void
     */
    private function get_limit()
    {


        // if($this->args['paged']
        $paged = intval($this->args['paged']) - 1;
        $posts_per_page = intval($this->args['posts_per_page']);

        $this->current_page =intval($this->args['paged']);

        if ($paged > 0) {
            $paged = $posts_per_page * $paged;
        }


        return ' ' . "LIMIT " . $paged . ", " . $posts_per_page;
    }
}
