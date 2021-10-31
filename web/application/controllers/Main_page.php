<?php

use System\Libraries\Core as SI_Core;
use Model\Boosterpack_model;
use Model\Post_model;
use Model\User_model;
use Model\Login_model;
use Model\Comment_model;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{

    public function __construct()
    {

        parent::__construct();

        if (is_prod())
        {
            die('In production it will be hard to debug! Run as development environment!');
        }

        $this->load->library('form_validation');
    }

    public function index()
    {
        $user = User_model::get_user();

        App::get_ci()->load->view('main_page', ['user' => User_model::preparation($user, 'default')]);
    }

    public function get_all_posts()
    {
        $posts =  Post_model::preparation_many(Post_model::get_all(), 'default');
        return $this->response_success(['posts' => $posts]);
    }

    public function get_boosterpacks()
    {
        $posts =  Boosterpack_model::preparation_many(Boosterpack_model::get_all(), 'default');
        return $this->response_success(['boosterpacks' => $posts]);
    }

    public function login()
    {
        $this->form_validation->set_rules('login', 'Login', 'required|trim');
        $this->form_validation->set_rules('password', 'Password', 'required|trim');

        if(! $this->form_validation->run()) {
            return $this->response_error( SI_Core::RESPONSE_GENERIC_WRONG_PARAMS, $this->form_validation->error_array(), 422);
        }

        $post = $this->input->post();
        $clean = $this->security->xss_clean($post);

        Login_model::login($clean['login'], $clean['password']);

        return User_model::is_logged()
            ? $this->response_success(['user' => User_model::preparation(User_model::get_user())])
            : $this->response_error("Invalid login or password");
    }

    public function logout()
    {
        Login_model::logout();
        redirect(base_url());
    }

    public function comment()
    {
        if (!User_model::is_logged()){
            return $this->response_error(SI_Core::RESPONSE_GENERIC_NEED_AUTH, [], 401);
        }

        $this->form_validation->set_rules('postId', 'Post', 'required|integer|trim');
        $this->form_validation->set_rules('replyId', 'Reply', 'trim|integer');
        $this->form_validation->set_rules('commentText', 'Comment', 'required|trim');

        if(! $this->form_validation->run()) {
            return $this->response_error( SI_Core::RESPONSE_GENERIC_WRONG_PARAMS, $this->form_validation->error_array(), 422);
        }

        $post = new Post_model($this->input->post('postId'));

        if (!$post->is_loaded()) {
            return $this->response_error( SI_Core::RESPONSE_GENERIC_NO_DATA, [], 404);
        }

        $clean = $this->security->xss_clean($this->input->post());

        $comment = Comment_model::create([
            'user_id' => User_model::get_session_id(),
            'assign_id' => $post->get_id(),
            'reply_id' => $clean['replyId'] ?? null,
            'text' => $clean['commentText'],
            'likes' => 0,
        ]);

        return $this->response_success(['comment' => Comment_model::preparation($comment)]);
    }

    public function like_comment(int $comment_id)
    {
        // TODO: task 3, лайк комментария
    }

    public function like_post(int $post_id)
    {
        // TODO: task 3, лайк поста
    }

    public function add_money()
    {
        // TODO: task 4, пополнение баланса

        $sum = (float)App::get_ci()->input->post('sum');

    }

    public function get_post(int $post_id) {
        $post = new Post_model($post_id);

        if (!$post->is_loaded()) {
            return $this->response_error(SI_Core::RESPONSE_GENERIC_NO_DATA, [], 404);
        }

        return $this->response_success(['post' => Post_model::preparation($post, 'full_info')]);
    }

    public function buy_boosterpack()
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        // TODO: task 5, покупка и открытие бустерпака
    }





    /**
     * @return object|string|void
     */
    public function get_boosterpack_info(int $bootserpack_info)
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }


        //TODO получить содержимое бустерпака
    }
}
