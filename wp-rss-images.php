<?php
/*
  Plugin Name: WP RSS Images
  Plugin URI: http://web-argument.com/wp-rss-images-wordpress-plugin/
  Description: Add feature images to your blog rss.
  Version: 1.1
  Author: Alain Gonzalez
  Author URI: http://web-argument.com/
 */

function wp_rss_img_get_options($default = false)
{
    $rss_img_default_op = array(
        "size" => "thumbnail",
        "rss" => 0,
        "rss2" => 1,
        "media" => 0,
        "enclosure" => 1,
        "version" => "1.1"
    );

    if ($default) {
        update_option('wp_rss_img_op', $rss_img_default_op);
        return $rss_img_default_op;
    }

    $options = get_option('wp_rss_img_op');
    if (isset($options)) {
        if (isset($options['version'])) {
            $chk_version = version_compare("1.1", $options['version']);
            if ($chk_version == 0)
                return $options;
            else if ($chk_version > 0)
                $options = $rss_img_default_op;
        } else {
            $options = $rss_img_default_op;
        }
    }
    update_option('wp_rss_img_op', $options);
    return $options;
}
add_action('admin_menu', 'wp_rss_img_menu');
add_action("do_feed_rss", "wp_rss_img_do_feed", 5, 1);
add_action("do_feed_rss2", "wp_rss_img_do_feed", 5, 1);
add_action('bbp_feed', 'wp_rss_img_do_feed');
add_action('bbp_feed', 'wp_rss_img_adding_yahoo_media_tag');
add_action('bbp_feed_item', 'wp_rss_img_include');

//TODO: make it optional to add the image to the content as well (like all the other plugins seem to do..)
function wp_rss_img_in_content($content)
{
    global $post;
    if ($post) {
        $image = get_the_post_thumbnail($post->ID, 'medium', array('style' => 'float:left;margin-right:5px;'));
    }
    if (!$image) {
        $image_url = wp_rss_img_url();
        $image = '<img src="' . $image_url .
            '" style="float:left;margin-right:5px;" />';
    }
    return $image . $content;
}
add_filter('the_excerpt_rss', 'wp_rss_img_in_content', 50, 1);
add_filter('the_content_feed', 'wp_rss_img_in_content', 50, 1);

function wp_rss_img_do_feed($for_comments = false)
{
    if (!$for_comments) {
        $options = wp_rss_img_get_options();
        if ($options['media']) {
            add_action('rss2_ns', 'wp_rss_img_adding_yahoo_media_tag');
        }
        if ($options['rss'])
            add_action('rss_item', 'wp_rss_img_include');
        if ($options['rss2'])
            add_action('rss2_item', 'wp_rss_img_include');

        add_filter('bbp_get_topic_content', 'wp_rss_img_in_content', 50, 1);
        add_filter('bbp_get_reply_content', 'wp_rss_img_in_content', 50, 1);
    }
}

function wp_rss_img_include()
{
    $options = wp_rss_img_get_options();
    $media = $options['media'];
    $enclosure = $options['enclosure'];
    //$image_size = $options['size'];
    $image_size = 'large';
    $filesize = 0;
    $image_url = wp_rss_img_url($image_size);

    if (!empty($image_url) && ($enclosure || $media)) {

        $uploads = wp_upload_dir();
        $url = parse_url($image_url);
        $path = $uploads['basedir'] . preg_replace('/.*uploads(.*)/', '${1}', $url['path']);

        if (file_exists($path)) {
            $filesize = filesize($path);
            $url = $path;
        }
        if (!$filesize) {
            //don't try to get headers for external images, to avoid excessive waits and errors
            $site_url = network_site_url();
            //$pos = strpos( $image_url, $site_url );
            //$issite = ($pos === FALSE);
            if (strpos($image_url, $site_url) !== FALSE) {
                try {
                    $ary_header = get_headers($image_url, 1);
                    if (isset($ary_header['Content-Length'])) {
                        $filesize = $ary_header['Content-Length'];
                    }
                } catch (Exception $e) {
                    error_log('unable to get size of "' . $image_url . '" due to error: ' . "\n" . $e->getMessage());
                }
            }
            $url = $image_url;
        }

        if ($enclosure)
            echo '<enclosure url="' . str_replace('https', 'http', $image_url) .
            '" length="' . $filesize . '" type="image/jpg" />'; //TODO: use right type not assume jpg			
            
//don't try to get the image size if we couldn't get the file size
        if ($media && $filesize) {
            try {
                list($width, $height, $type, $attr) = getimagesize($url);
                echo '<media:content url="' . $image_url . '" width="' . $width . '" height="' . $height .
                '" medium="image" type="' . image_type_to_mime_type($type) . '" />';
            } catch (Exception $e) {
                error_log('unable to get image size of "' . $image_url . '" due to error: ' .
                    "\n" . $e->getMessage());
            }
        }
        //add inkston image fallback where no attached image
    } elseif (function_exists('inkston_catch_image')) {
        $image_url = inkston_catch_image();
        //don't try to get headers for external images, to avoid excessive waits and errors
        if (strpos($image_url, network_site_url() !== FALSE)) {
            try {
                $ary_header = get_headers($image_url, 1);
                $filesize = $ary_header['Content-Length'];
            } catch (Exception $e) {
                error_log('unable to get size of "' . $image_url . '" due to error: ' . "\n" . $e->getMessage());
            }
        }
        if ($enclosure)
            echo '<enclosure url="' . str_replace('https', 'http', $image_url) .
            '" length="' . $filesize .
            '" type="image/jpg" />';
        if ($media && $filesize) {
            try {
                list($width, $height, $type, $attr) = getimagesize($url);
                echo '<media:content url="' . $image_url . '" width="' . $width . '" height="' . $height . '" medium="image" type="' . image_type_to_mime_type($type) . '" />';
            } catch (Exception $e) {
                error_log('unable to get size of "' . $image_url . '" due to error: ' . "\n" . $e->getMessage());
            }
        }
    }
}

function wp_rss_img_adding_yahoo_media_tag()
{

    echo 'xmlns:media="http://search.yahoo.com/mrss/"';
}

function wp_rss_img_url($size = 'medium')
{
    global $post;
    if (function_exists('has_post_thumbnail') && has_post_thumbnail($post->ID)) {
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if (!empty($thumbnail_id))
            $img = wp_get_attachment_image_src($thumbnail_id, $size);
    } else {
        $attachments = get_children(array(
            'post_parent' => $post->ID,
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'numberposts' => 1));
        if ($attachments == true) {
            foreach ($attachments as $id => $attachment) :
                $img = wp_get_attachment_image_src($id, $size);
            endforeach;
        }
    }
    if (isset($img)) {
        return $img[0];
    } elseif (function_exists('inkston_catch_image')) {
        return inkston_catch_image();
    }
}

function wp_rss_img_menu()
{
    add_options_page('WP RSS Images', 'WP RSS Images', 'administrator', 'wp-rss-image', 'wp_rss_image_setting');
}

function wp_rss_image_setting()
{

    $options = wp_rss_img_get_options();

    if (isset($_POST['Submit'])) {
        $newoptions = array();
        $newoptions['media'] = isset($_POST['media']) ? $_POST['media'] : 0;
        $newoptions['enclosure'] = isset($_POST['enclosure']) ? $_POST['enclosure'] : 0;
        $newoptions['rss'] = isset($_POST['rss']) ? $_POST['rss'] : 0;
        $newoptions['rss2'] = isset($_POST['rss2']) ? $_POST['rss2'] : 0;
        $newoptions['size'] = isset($_POST['size']) ? $_POST['size'] : $options['size'];
        $newoptions['version'] = $options['version'];

        if ($options != $newoptions) {
            $options = $newoptions;
            update_option('wp_rss_img_op', $options);

            ?>
            <div class="updated"><p><strong><?php _e('Options saved.', 'mt_trans_domain'); ?></strong></p></div>         
        <?php } ?>
    <?php
    }

    $media = $options['media'];
    $enclosure = $options['enclosure'];
    $rss = $options['rss'];
    $rss2 = $options['rss2'];
    $image_size = $options['size'];
    $version = $options['version'];

    ?>

    <div class="wrap">   

      <form method="post" name="options" target="_self">

        <h2><?php _e("WP RSS Images Setting") ?></h2>
        <h3><?php _e("Select the size of the images") ?></h3>
        <p><?php _e("The plugin uses the Posts feature images or the first image added to the post gallery. You can change the dimension of this sizes under Settings/ Media.") ?></p>
        <table width="100%" cellpadding="10" class="form-table">

          <tr valign="top">
            <td width="200" align="right">
              <input type="radio" name="size" id="radio" value="thumbnail" <?php if ($image_size == 'thumbnail') echo "checked=\"checked\""; ?>/>
            </td>
            <td align="left" scope="row"><?php _e("Thumbnail") ?></td>
          </tr>
          <tr valign="top">
            <td width="200" align="right">
              <input name="size" type="radio" id="radio" value="medium" <?php if ($image_size == 'medium') echo "checked=\"checked\""; ?> />
            </td> 
            <td align="left" scope="row"><?php _e("Medium Size") ?></td>
          </tr>
          <tr valign="top">
            <td width="200" align="right">
              <input type="radio" name="size" id="radio" value="full" <?php if ($image_size == 'full') echo "checked=\"checked\""; ?>/>
            </td> 
            <td align="left" scope="row"><?php _e("Full Size") ?></td>
          </tr>
        </table>

        <h3> <?php _e("Apply to: ") ?></h3>
        <table width="100%" cellpadding="10" class="form-table">  
          <tr valign="top">
            <td width="200" align="right"><input name="rss" type="checkbox" id="rss" value="1" 
    <?php if ($rss) echo "checked" ?> /></td>
            <td align="left" scope="row"><?php _e("RSS") ?>   <a href="<?php echo get_bloginfo('rss_url'); ?> " title="<?php bloginfo('name'); ?> - rss" target="_blank"><?php echo get_bloginfo('rss_url'); ?> </a> </td>
          </tr>
          <tr valign="top">
            <td width="200" align="right"><input name="rss2" type="checkbox" id="rss2" value="1" 
    <?php if ($rss2) echo "checked" ?> /></td>
            <td align="left" scope="row"><?php _e("RSS 2") ?>    <a href="<?php echo get_bloginfo('rss2_url'); ?> " title="<?php bloginfo('name'); ?> - rss2" target="_blank"><?php echo get_bloginfo('rss2_url'); ?> </a> </td>
          </tr>    
        </table>

        <h3> <?php _e("Include: ") ?></h3>
        <table width="100%" cellpadding="10" class="form-table">  
          <tr valign="top">
            <td width="200" align="right"><input name="enclosure" type="checkbox" value="1" 
    <?php if ($enclosure) echo "checked" ?> /></td>
            <td align="left" scope="row"><?php _e("Enclosure Tag") ?></td>
          </tr>
          <tr valign="top">
            <td width="200" align="right"><input name="media" type="checkbox" value="1" 
    <?php if ($media) echo "checked" ?> /></td>
            <td align="left" scope="row"><?php _e("Media Tag") ?></td>
          </tr>    
        </table>

        <p class="submit">
          <input type="submit" name="Submit" value="<?php _e("Update") ?>" />
        </p>

      </form>
    </div>

<?php } ?>