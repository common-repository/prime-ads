<?php

add_action('add_meta_boxes', 'add_offer_post_meta_box');
add_action('save_post', 'offer_post_meta_box_save');

function add_offer_post_meta_box(){
    add_meta_box('offer_post-meta-box', 'Офферная статья', 'offer_post_meta_box', 'post', 'side', 'high', null);
}

function offer_post_meta_box(){
    global $post;
    wp_nonce_field('offer_post_meta_box_save', 'offer_post_meta_box_nonce');
    $post_id = $post->ID;
    ?>
    <div class="meta_offer_post">
        <div class="meta_offer_post_input">
            <?$value = get_post_meta($post_id, 'is_offer_post', true);?>
            <label><input type="checkbox" name="is_offer_post" <?if($value) echo 'checked';?> value="on"> Эта статья офферная</label>
        </div>
    </div>
<?php }

function offer_post_meta_box_save($post_id){
    if (!isset($_POST['offer_post_meta_box_nonce'])) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    if (isset($_POST['is_offer_post']) && $_POST['is_offer_post']) {
        update_post_meta($post_id, 'is_offer_post', $_POST['is_offer_post']);
    } else {
        delete_post_meta($post_id, 'is_offer_post');
    }
}