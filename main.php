<?php
/*
Plugin Name: yuki-comment-form
Description: yuki-comment-form
Author: ふぁ
Version: 0.1
*/

class yuki_comment_form
{

    function __construct()
    {
        if (!session_id()) session_start();
        $this->time = (string)time();
        $this->csrf_token = uniqid('', true);
        $_SESSION["csrf_token_" . $this->time] = $this->csrf_token;
        add_filter('comment_form_default_fields', array($this, 'comment_form_remove'), 999999, 2);
        add_filter('comment_form_defaults', array($this, 'comment_form_change'), 999999, 2);
        add_filter('pre_comment_approved', array($this, 'comment_form_notice'), 0, 2);
        add_filter('wp_enqueue_scripts', array($this, 'custom_stylesheet'));
        add_action('admin_menu', array($this, 'add_pages'));
    }

    function notify_message($message)
    {
        wp_nonce_field('shoptions');
        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;
        if (!isset($show_text["token"])) return;
        $notifyToken = $show_text["token"];

        $data = http_build_query(['message' => $message], '', '&');
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Authorization: Bearer ' . $notifyToken . "\r\n"
                    . "Content-Type: application/x-www-form-urlencoded\r\n"
                    . 'Content-Length: ' . strlen($data)  . "\r\n",
                'content' => $data,
            ]
        ];

        $context = stream_context_create($options);
        $resultJson = file_get_contents('https://notify-api.line.me/api/notify', false, $context);
        $resultArray = json_decode($resultJson, true);
        if ($resultArray['status'] != 200) {
            return false;
        }
        return true;
    }

    function add_pages()
    {
        add_menu_page('yuki-comment-form', 'yuki-comment-form',  'level_8', __FILE__, array($this, 'show_text_option_page'), '', 26);
    }

    function comment_form_remove($arg)
    {
        if (isset($arg['url'])) $arg['url'] = '';
        if (isset($arg['email'])) $arg['email'] = '';
        if (isset($arg['author'])) $arg['author'] = '';
        if (isset($arg['cookies'])) $arg['cookies'] = '';
        return $arg;
    }


    function comment_form_change($defaults)
    {
        // $defaults['comment_notes_before'] = '<p class="comment-notes"><span id="email-notes">' . _('URLは使用禁止です。') . '</span></p>';
        $defaults['title_reply_to'] = _('返信する');
        $defaults['cancel_reply_link'] = _('返信をキャンセル');
        $defaults['submit_field'] .= '<input type="hidden" name="csrf_token" value="' . $this->csrf_token . '">';
        $defaults['submit_field'] .= '<input type="hidden" name="csrf_time" value="' . $this->time . '">';
        $defaults['logged_in_as'] = '<p class="logged-in-as"><a href="" aria-label=" プロフィールを編集">ログイン中</a>のため現在コメント機能は使用できません。<a href="https://fnjpnews.com/wp-login.php?action=logout&amp;redirect_to=https%3A%2F%2Ffnjpnews.com%2FNews%2F7888&amp;_wpnonce=2dca9fc999">ログアウトしますか？</a></p>';
        return $defaults;
    }
    function comment_form_notice($commentdata, $defaults)
    {
        $this->notify_message(_("ステータス") . "：" . $commentdata . "\n" . _("記事") . "url：" . home_url('/') . "?p=" . $defaults["comment_post_ID"] . "\nip" . _("アドレス") . "：" . $defaults["comment_author_IP"] . "\n" . _("コメント") . "：" . $defaults["comment_content"]);
        if ($commentdata != 1) {
            $this->notify_message(_("判定：スパム"));
            $this->notify_message(_("他プラグインの判定"));
            return "spam";
        }
        if ($_POST["csrf_token"] != $_SESSION["csrf_token_" . $_POST["csrf_time"]]) {
            $this->notify_message(_("判定：スパム"));
            $this->notify_message(_("csrfトークンエラー"));
            return "spam";
        }
        if ($defaults["comment_author"] != null) {
            $this->notify_message(_("判定：スパム"));
            $this->notify_message(_("本来入力されていないはずのフォームが入力されている 該当id:comment_author"));
            return "spam";
        }
        if ($defaults["comment_author_email"] != null) {
            $this->notify_message(_("判定：スパム"));
            $this->notify_message(_("本来入力されていないはずのフォームが入力されている 該当id:comment_author_email"));
            return "spam";
        }
        if ($defaults["comment_author_url"] != null) {
            $this->notify_message(_("判定：スパム"));
            $this->notify_message(_("本来入力されていないはずのフォームが入力されている 該当id:comment_author_url"));
            return "spam";
        }
        if ($defaults["user_ID"] != null) {
            $this->notify_message(_("判定：スパム"));
            $this->notify_message(_("本来入力されていないはずのフォームが入力されている 該当id:user_ID"));
            return "spam";
        }
        $this->notify_message(_("判定：認証"));
    }

    function custom_stylesheet()
    {
        wp_enqueue_style(
            'yuki_comment_form_custom_stylesheet',
            plugins_url('custom-stylesheet.css', __FILE__)
        );
    }
    function show_text_option_page()
    {
        wp_nonce_field('shoptions');
        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;

        if (isset($_POST['showtext_options'])) {
            $opt = $_POST['showtext_options'];
            update_option('showtext_options', $opt);
        }

        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;
?>
<h2>LineNotify<?php _e('設定') ?></h2>
<p>LineNotify<?php _e('に通知を送ります。送信する先の') ?>token<?php _e('を入力してください。') ?></p>
<p><?php _e('空白にするとオフにします') ?></p>
<form action="" method="post">
    <input name="showtext_options[token]" type="text" id="inputtext" placeholder="token"
        value="<?php echo $show_text["token"] ?>" />
    <input type="submit" name="Submit" class="button-primary" value="<?php _e('変更を保存') ?>" />
</form>
<?php
        if (isset($_POST['showtext_options'])) {
        ?>
<p><?php _e('保存しました'); ?></p>
<?php
        }
    }
}
$showtext = new yuki_comment_form;