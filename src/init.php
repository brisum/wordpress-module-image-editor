<?php

add_filter('wp_image_editors', 'theme_module_wp_image_editors');
function theme_module_wp_image_editors($editors)
{
	array_unshift($editors, 'Brisum\Wordpress\ImageEditor\Gd');
	return $editors;
}
