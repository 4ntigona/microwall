<?php
/*
Plugin Name: Microwall
Description: Um plugin de microwall para restringir acesso a conteúdo baseado em tags.
Version: 1.0
Author: Seu Nome
License: GPL2
*/

// Adicionar um item de menu no painel do WordPress
function microwall_add_menu_item() {
    $capability = 'manage_options';
    $slug = 'microwall-settings';
    $title = 'Microwall';
    $callback = 'microwall_render_plugin_page';
    $icon = 'dashicons-lock';
    $position = 20;

    // Add menu page
    add_menu_page($title, $title, $capability, $slug, $callback, $icon, $position);

    // Add submenu page
    $parent_slug = $slug;
    $page_title = 'Settings';
    $menu_title = 'Settings';
    $submenu_slug = 'microwall-settings';
    $submenu_callback = 'microwall_render_plugin_page';
    add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $submenu_slug, $submenu_callback);
}
add_action('admin_menu', 'microwall_add_menu_item');

// Adicionar scripts e estilos do Select2
function microwall_scripts() {
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
    wp_enqueue_style('microwall', plugin_dir_url(__FILE__) . 'assets/css/microwall.css', array(), '1.0');
}
add_action('admin_enqueue_scripts', 'microwall_scripts');

// Adicionar atributo de seleção múltipla e inicializar o Select2 no campo de seleção de tags
function microwall_render_tag_select_field() {
    $selected_tags = get_option('microwall_selected_tags', array());
    $all_tags = get_terms(array(
        'taxonomy' => 'post_tag',
        'hide_empty' => false,
        'orderby' => 'count',
        'order' => 'DESC',
    ));

    if (is_wp_error($all_tags)) {
        echo 'Erro ao carregar tags';
        return;
    }

    echo '<select name="microwall_selected_tags[]" class="microwall-tag-select" multiple="multiple">';
    foreach ($all_tags as $tag) {
        $selected = in_array($tag->term_id, $selected_tags) ? 'selected="selected"' : '';
        echo '<option value="' . $tag->term_id . '" ' . $selected . '>' . $tag->name . '</option>';
    }
    echo '</select>';

    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.microwall-tag-select').select2();
    });
    </script>
    <?php
}

// Função para renderizar a página de configuração do plugin
function microwall_render_plugin_page() {
    // Verificar se o usuário atual tem permissão para acessar a página de configuração
    if (!current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>Acesso negado! Você não tem permissão para acessar essa página.</p></div>';
        return;
    }

    // Salvar as configurações quando o formulário for enviado
    if (isset($_POST['microwall_settings_submit'])) {
        $selected_tags = isset($_POST['microwall_selected_tags']) ? $_POST['microwall_selected_tags'] : array();
        update_option('microwall_selected_tags', $selected_tags);
        echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
    }

    // Obter todas as tags (post_tag) existentes no site
    $tags = get_terms(array(
        'taxonomy' => 'post_tag',
        'hide_empty' => false
    ));

    ?>
    <div class="microwall-wrap">
        <h1>Configurações do Microwall</h1>
        <div class="microwall-section">

            <form method="post" action="">
                <h2>Configurar Restrição</h2>

                <div class="form_field">
                    <p>Selecione as tags abaixo para restringir o acesso ao conteúdo associado a essas tags:</p>

                    <?php microwall_render_tag_select_field(); ?>
                </div>

                <div class="form_field">
                    <input type="submit" name="microwall_settings_submit" class="button button-primary" value="Salvar Configurações">
                </div>
            </form>

        </div>

        <div class="microwall-section">

            <h2>Criar Novo Usuário</h2>
            <?php microwall_display_user_creation_form(); ?>

        </div>

        <div class="microwall-section">


            <?php microwall_render_porosity_settings(); ?>


        </div>

        <div class="microwall-section">

            <h2>Gerenciar Usuários</h2>
            <?php microwall_display_user_list(); ?>

        </div>
    </div>
    <?php
}

// // Função para renderizar a nova seção nas configurações do plugin
// function microwall_render_product_selection() {
//     // Verificar se o Woocommerce está instalado
//     if (!class_exists('WooCommerce')) {
//         // Woocommerce não está instalado
//         echo '<p>O plugin Woocommerce não está instalado.</p>';
//         echo '<a href="' . admin_url('plugin-install.php?s=woocommerce&tab=search&type=term') . '" class="button button-primary">Instalar Woocommerce</a>';
//     } elseif (!is_plugin_active('woocommerce/woocommerce.php')) {
//         // Woocommerce está instalado, mas não ativado
//         echo '<p>O plugin Woocommerce está instalado, mas não está ativado.</p>';
//         echo '<a href="' . admin_url('plugins.php') . '" class="button button-primary">Ativar Woocommerce</a>';
//     } else {
//         // Woocommerce está instalado e ativado
//         echo '<p>Selecione o produto para restringir o acesso:</p>';
//         microwall_render_product_select_field();
//     }
// }

// Adicionar campo de seleção de tags nas configurações do plugin
function microwall_add_settings_fields() {
    add_settings_section('microwall_settings_section', 'Configurações do Microwall', 'microwall_render_settings', 'microwall_settings');
    add_settings_field('microwall_selected_tags', 'Tags Restritas', '', 'microwall_settings', 'microwall_settings_section');
    // add_settings_section('microwall_product_section', 'Configurar Restrição de Produto', 'microwall_render_product_selection', 'microwall_settings');
    register_setting('microwall_settings', 'microwall_selected_tags', 'microwall_sanitize_selected_tags');
}
add_action('admin_init', 'microwall_add_settings_fields');

// Sanitizar as tags selecionadas
function microwall_sanitize_selected_tags($input) {
    if (is_array($input)) {
        return array_map('intval', $input);
    }

    return array();
}

// Registro das configurações
function microwall_register_settings() {
    register_setting('microwall-restriction-settings', 'microwall_restriction_tag');
}
add_action('admin_init', 'microwall_register_settings');

// Exibir a lista de usuários
function microwall_display_user_list() {
    $users = get_users(['role' => 'subscriber']);

    if (!empty($users)) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th class="date">Data de Assinatura</th>
                    <th class="date">Data Final</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) {
                    $start_date = get_user_meta($user->ID, 'microwall_start_date', true);
                    // transform $start_date from 2023-05-30 23:54:44 to 30/05/2023 @ 23:54
                    $start_date = date('d/m/Y | H:i', strtotime($start_date));

                    $end_date = get_user_meta($user->ID, 'microwall_end_date', true);
                    // transform $end_date from 2023-05-30 23:54:44 to 30/05/2023 @ 23:54
                    $end_date = date('d/m/Y | H:i', strtotime($end_date));

                    $status = get_user_meta($user->ID, 'microwall_subscription_status', true);

                    // add $status to span class with 'status-{status}' as class
                    $status = '<span class="status-' . $status . '">' . $status . '</span>';
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo get_edit_user_link($user->ID); ?>"><?php echo esc_html($user->display_name); ?></a>
                        </td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td class="date"><?php echo esc_html($start_date); ?></td>
                        <td class="date"><?php echo esc_html($end_date); ?></td>
                        <td><?php echo $status; ?></td>
                        <td>
                            <a class="button button-secondary" href="?page=microwall-settings&action=renew_subscription&user_id=<?php echo esc_attr($user->ID); ?>">Renovar</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
    } else {
        echo 'Nenhum usuário assinante encontrado.';
    }
}

// Exibir formulário de criação de usuário
function microwall_display_user_creation_form() {
    if (isset($_GET['action']) && $_GET['action'] === 'renew_subscription') {
        $user_id = $_GET['user_id'];
        $user = get_user_by('ID', $user_id);
        $start_date = get_user_meta($user_id, 'microwall_start_date', true);
        $end_date = get_user_meta($user_id, 'microwall_end_date', true);
        $status = (strtotime($end_date) >= current_time('timestamp')) ? 'Ativa' : 'Inativa';
        ?>
        <h3>Renovar Assinatura para <?php echo $user->display_name; ?></h3>
        <form method="post" action="?page=microwall-settings&action=update_subscription&user_id=<?php echo $user_id; ?>">
            <div class="form_field">
                <label for="subscription_duration">Tempo de Acesso:</label>
                <input type="number" name="subscription_duration" id="subscription_duration" min="1" required>
                <select name="subscription_duration_unit" id="subscription_duration_unit">
                    <option value="minutes">Minutos</option>
                    <option value="hours">Horas</option>
                    <option value="days">Dias</option>
                    <option value="months">Meses</option>
                    <option value="years">Anos</option>
                </select>
            </div>
            <input type="submit" class="button button-primary" value="Renovar Assinatura">
        </form>
        <p>Status da Assinatura: <?php echo $status; ?></p>
        <?php
    } else {
        ?>
        <form method="post" action="?page=microwall-settings&action=create_user">
            <div class="form_field">
                <label for="user_name">Nome:</label>
                <input type="text" name="user_name" id="user_name" required>
            </div>
            <div class="form_field">
                <label for="user_email">Email:</label>
                <input type="email" name="user_email" id="user_email" required>
            </div>
            <div class="form_field">
                <label for="subscription_duration">Tempo de Acesso:</label>
                <input type="number" name="subscription_duration" id="subscription_duration" min="1" required>
                <select name="subscription_duration_unit" id="subscription_duration_unit">
                    <option value="minutes">Minutos</option>
                    <option value="hours">Horas</option>
                    <option value="days">Dias</option>
                    <option value="months">Meses</option>
                    <option value="years">Anos</option>
                </select>
            </div>
            <input type="submit" class="button button-primary" value="Criar Usuário">
        </form>
        <?php
    }
}

// Ação de criação ou renovação de assinatura de usuário
function microwall_manage_subscription_action() {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        // set timezone to São Paulo to get the correct time
        date_default_timezone_set('America/Sao_Paulo');

        switch ($action) {
            case 'create_user':
                if (isset($_POST['user_name']) && isset($_POST['user_email']) && isset($_POST['subscription_duration']) && isset($_POST['subscription_duration_unit'])) {
                    $user_name = $_POST['user_name'];
                    $user_email = $_POST['user_email'];
                    $subscription_duration = $_POST['subscription_duration'];
                    $subscription_duration_unit = $_POST['subscription_duration_unit'];

                    $start_date = current_time('mysql');
                    $end_date = date('Y-m-d H:i:s', strtotime("+$subscription_duration $subscription_duration_unit"));

                    // Check if the user email already exists
                    $user = get_user_by('email', $user_email);
                    if ($user) {
                        // If the user exists, check if the user has the microwall_end_date metadata
                        $user_end_date = get_user_meta($user->ID, 'microwall_end_date', true);
                        if ($user_end_date) {
                            // If the user has the microwall_end_date metadata, check if the end date is greater than the current date
                            if (strtotime($user_end_date) > time()) {
                                // If the end date is greater than the current date, the user has an active subscription
                                echo 'Erro ao criar o usuário. Já existe um usuário com essa conta de e-mail, e a assinatura dele ainda está ativa.';
                            } else {
                                // If the end date is less than the current date, the user doesn't have an active subscription
                                $new_user_id = $user->ID;
                            }
                        } else {
                            // If the user doesn't have the microwall_end_date metadata, the user doesn't have an active subscription
                            $new_user_id = $user->ID;
                        }
                    } else {
                        // If the user doesn't exist, create the user
                        $new_user_id = wp_create_user($user_email, wp_generate_password(), $user_email);
                        if (!is_wp_error($new_user_id)) {
                            wp_update_user([
                                'ID' => $new_user_id,
                                'display_name' => $user_name
                            ]);
                            update_user_meta($new_user_id, 'microwall_start_date', $start_date);
                            update_user_meta($new_user_id, 'microwall_end_date', $end_date);
                            update_user_meta($new_user_id, 'microwall_subscription_status', 'Ativa');

                            // Enviar email de notificação para criação de senha
                            wp_new_user_notification($new_user_id, null, 'both');
                        }
                    }

                    if (isset($new_user_id)) {
                        echo 'Usuário criado com sucesso!';
                    } else {
                        echo 'Erro ao criar o usuário.';
                    }
                }
                break;

            case 'update_subscription':
                if (isset($_GET['user_id']) && isset($_POST['subscription_duration']) && isset($_POST['subscription_duration_unit'])) {
                    $user_id = $_GET['user_id'];
                    $subscription_duration = $_POST['subscription_duration'];
                    $subscription_duration_unit = $_POST['subscription_duration_unit'];

                    $start_date = current_time('mysql');
                    $end_date = date('Y-m-d H:i:s', strtotime("+$subscription_duration $subscription_duration_unit"));

                    update_user_meta($user_id, 'microwall_start_date', $start_date);
                    update_user_meta($user_id, 'microwall_end_date', $end_date);
                    update_user_meta($user_id, 'microwall_subscription_status', 'Ativa');

                    echo 'Assinatura renovada com sucesso!';
                } else {
                    echo 'Erro ao renovar a assinatura.';
                }
                break;

            case 'cancel_subscription':
                if (isset($_GET['user_id'])) {
                    $user_id = $_GET['user_id'];

                    // set the endtime to the current time
                    $end_date = current_time('mysql');

                    update_user_meta($user_id, 'microwall_end_date', $end_date);
                    update_user_meta($user_id, 'microwall_subscription_status', 'Cancelada');

                    echo 'Assinatura cancelada com sucesso!';
                } else {
                    echo 'Erro ao cancelar a assinatura.';
                }
                break;
        }
    }
}

add_action('admin_init', 'microwall_manage_subscription_action');

// Exibir campos personalizados na página de edição do usuário
function microwall_show_user_meta_fields($user) {
    $start_date = get_user_meta($user->ID, 'microwall_start_date', true);
    $end_date = get_user_meta($user->ID, 'microwall_end_date', true);
    $subscription_status = get_user_meta($user->ID, 'microwall_subscription_status', true);

    if (!$start_date) {
        $start_date = 'N/A';
    }

    if (!$end_date) {
        $end_date = 'N/A';
    }

    if (!$subscription_status) {
        $subscription_status = 'N/A';
    }

    ?>
    <table class="form-table">
        <tr>
            <th><label>Data de Assinatura</label></th>
            <td>
                <input type="text" value="<?php echo $start_date; ?>" class="regular-text" readonly>
            </td>
        </tr>
        <tr>
            <th><label>Data Final</label></th>
            <td>
                <input type="text" value="<?php echo $end_date; ?>" class="regular-text" readonly>
            </td>
        </tr>
        <tr>
            <th><label>Status da Assinatura</label></th>
            <td>
                <input type="text" value="<?php echo $subscription_status; ?>" class="regular-text" readonly>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'microwall_show_user_meta_fields');
add_action('edit_user_profile', 'microwall_show_user_meta_fields');


// Verificar se a assinatura de um usuário está ativa
function microwall_is_subscription_active($user_id) {
    $end_date = get_user_meta($user_id, 'microwall_end_date', true);

    // Verificar se a data final da assinatura é igual ou posterior à data atual
    if ($end_date && strtotime($end_date) >= current_time('timestamp')) {
        // return true; // Assinatura ativa
        // keep the user meta set to active
        update_user_meta($user_id, 'microwall_subscription_status', 'Ativa');
        return true;
    }

    // return false; // Assinatura inativa

    // update the user meta to inactive
    update_user_meta($user_id, 'microwall_subscription_status', 'Inativa');
    return false;
}

// add a column to the users table to display the subscription status (microwall_subscription_status)

function microwall_add_user_subscription_status_column($columns) {
    $columns['microwall_subscription_status'] = 'Status da Assinatura';
    return $columns;
}

add_filter('manage_users_columns', 'microwall_add_user_subscription_status_column');

function microwall_show_user_subscription_status_column_content($value, $column_name, $user_id) {
    if ('microwall_subscription_status' == $column_name) {
        $subscription_status = get_user_meta($user_id, 'microwall_subscription_status', true);
        return $subscription_status;
    }
    return $value;
}

add_action('manage_users_custom_column', 'microwall_show_user_subscription_status_column_content', 10, 3);

// create a daily cron-job to check for expired subscriptions and update the user's subscription status. use the wp_schedule_event function to schedule the cron-job. set timezone to São Paulo, Brazil

function microwall_check_expired_subscriptions() {
    $users = get_users(array(
        'role' => 'subscriber'
    ));

    foreach ($users as $user) {
        $user_id = $user->ID;
        $subscription_status = get_user_meta($user_id, 'microwall_subscription_status', true);

        if ($subscription_status == 'Ativa' && !microwall_is_subscription_active($user_id)) {
            update_user_meta($user_id, 'microwall_subscription_status', 'Inativa');
        }
    }
}

add_action('microwall_check_expired_subscriptions', 'microwall_check_expired_subscriptions');

function microwall_schedule_cron_jobs() {
    if (!wp_next_scheduled('microwall_check_expired_subscriptions')) {
        wp_schedule_event(current_time('timestamp'), 'hourly', 'microwall_check_expired_subscriptions');
    }
}

add_action('wp', 'microwall_schedule_cron_jobs');

// create a function to remove the cron-job when the plugin is uninstalled

function microwall_remove_cron_jobs() {
    wp_clear_scheduled_hook('microwall_check_expired_subscriptions');
}

register_uninstall_hook(__FILE__, 'microwall_remove_cron_jobs');

// include filter access file
require_once plugin_dir_path(__FILE__) . 'assets/php/filter-access.php';

// include assets/php/woocommerce.php file
require_once plugin_dir_path(__FILE__) . 'assets/php/woocommerce.php';

// include assets/php/porosity.php file
require_once plugin_dir_path(__FILE__) . 'assets/php/porosity.php';