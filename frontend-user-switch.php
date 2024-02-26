<?php
/**
 * Frontend User Switch
 *
 * @author Frank Collins
 *
 * Plugin Name:       Frontend User Switch
 * Description:       Front End User Swither
 * Version:           1.0
 * Author:            Frank Collins
 */

class Frontend_User_Switch
{
    function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'user_switcher_endpoint'));
        add_action('wp_loaded', array($this, 'switch_user'));
        add_action('acf/init', array($this, 'on_acf_load'));
    }

    public function init()
    {
        if(is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if (current_user_can('manage_options', $current_user->ID) || current_user_can('sales_rep', $current_user->ID)) {
                add_filter('woocommerce_account_menu_items', array($this, 'add_user_switcher_tab'));
                add_action('woocommerce_account_user-switcher_endpoint', array($this, 'users_list_html'));
            }
        }
    }

    public function on_acf_load()
    {
        add_filter('acf/load_field/name=customer_sales_rep', array($this, 'add_sales_rep_choices'));
    }

    public function add_user_switcher_tab($menu_items)
    {
        $menu_items['user-switcher'] = __('Users', 'frank-collins-designs');
        return $menu_items;
    }


    public function user_switcher_endpoint()
    {
        add_rewrite_endpoint('user-switcher', EP_ROOT | EP_PAGES);
    }

    public function get_users()
    {
        $current_user_id = 0;
        // Get the current user's ID
        if(is_user_logged_in()) {
            $current_user_id = get_current_user_id();
        }
        
        $args = array(
        'meta_query' => array(
        array(
        'key'   => 'customer_sales_rep',
        'value' => $current_user_id,
        ),
            ),
        );
        
        // Create a user query
        $userQuery = new WP_User_Query($args);
        $all_users = $userQuery->get_results();

        return $all_users;
    }

    public function get_users_list($paged = 1, $users_per_page = 10)
    {    
        $all_users = $this->get_users();
        $total_users = count($all_users);
        $total_pages = ceil($total_users / $users_per_page);
        $paged = max(1, min($paged, $total_pages));
        $offset = ($paged - 1) * $users_per_page;
        $paged_users = array_slice($all_users, $offset, $users_per_page);

        return array(
        'users' => $paged_users,
        'total_pages' => $total_pages,
        'current_page' => $paged,
        'total_users' => $total_users,
        );
    }

    public function users_list_html($paged = 1, $users_per_page = 10)
    {
        if(isset($_GET['paged']) && isset($_get['per_page'])) {
            $paged = intval($_GET['paged']);
            $users_per_page = intval($_GET['per_page']);
        }

        $user_list = $this->get_users_list($paged, $users_per_page);
        $account_link = get_permalink(wc_get_page_id('myaccount'));
        $account_link = rtrim($account_link, "/");
        if (!empty($user_list['users'])) {
            foreach ($user_list['users'] as $user) {
                $user_id = $user->ID;
                $first_name = get_user_meta($user->ID, 'first_name', true);
                $last_name = get_user_meta($user->ID, 'last_name', true);
                $user_name = $user->display_name;
                $dashboard_link = admin_url('user-edit.php?user_id=' . $user_id);
                ?>
                <form method="post" action="<?php echo esc_url($account_link); ?>/user-switcher">
                    <div class="user-switcher__user-info">
                        <p><b>Full Name : <?php echo esc_html($first_name). ' ' . esc_html($last_name);?></b></p>
                        <p><b>User Name : <?php echo esc_html($user_name); ?></b></p>
                    </div>
                    <input type="hidden" name="user-switcher-user-id" value="<?php echo esc_attr($user_id);?>">
                <?php
                echo wp_nonce_field(
                    'user-switch-' . $user_id,
                    'user-switcher-nonce',
                    false,
                    false 
                );
                ?>
                    <p class="user-switcher__user-options">
                        <button type="submit">Switch to User</button>
                <?php if(current_user_can('manage_options')) :?>
                        <button type="button"><a style="color:#fff;" target="_blank" href="<?php echo esc_url($dashboard_link);?>">View in Dashboard</a></button>
                <?php endif; ?>
                    </p>
                </form>
                <hr>
                <?php
            }

            echo paginate_links(
                array(
                'total' => $user_list['total_pages'],
                'current' => $user_list['current_page'],
                'prev_next' => true,
                'prev_text' => __('&laquo; Previous'),
                'next_text' => __('Next &raquo;'),
                'format' => '?paged=%#%',
                )
            );
        } else {
            echo "<p><b>No Customers found</b></p>";
        }

        
    }

    public function switch_user()
    {
        if(isset($_POST)) {
            if(isset($_POST['user-switcher-nonce']) && isset($_POST['user-switcher-user-id'])) {
                $switcher_user_id = intval($_POST['user-switcher-user-id']);
                $nonce = sanitize_text_field($_POST['user-switcher-nonce']);
                $is_valid = wp_verify_nonce($nonce, 'user-switch-' . $switcher_user_id);
                $account_link = get_permalink(wc_get_page_id('myaccount'));
                if ($is_valid) {
                    wp_logout();
                    wp_set_current_user($switcher_user_id);
                    wp_set_auth_cookie($switcher_user_id);
            
                    // Redirect or perform other actions as needed
                    wp_redirect($account_link);
                    exit;
                } else {
                    // handle invalid nonce.
                }
            }
        }
        return false;
    }

    public function add_sales_rep_choices( $field )
    {
        if ($field['name'] === 'customer_sales_rep') {
            $sales_reps = get_users(array('role' => 'sales_rep'));

            $admins = get_users(array('capability' => 'manage_options'));
            $all_reps = array_merge($sales_reps, $admins);

            // Remove duplicates based on user ID
            $all_reps = array_unique($all_reps, SORT_REGULAR);
    
            $choices = array();
    
            foreach ($all_reps as $rep) {
                $choices[$rep->ID] = $rep->display_name;
            }

            $field['choices'] = $choices;
        }
        return $field;
    }
}

$user_switch_instance = new Frontend_User_Switch();

