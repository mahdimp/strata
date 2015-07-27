<?php

namespace Strata\Model\Validator;

class PostexistsValidator extends Validator {

    protected $_errorMessage = "This post could not be found.";

    public function test($value, $context)
    {
        $postId = (int)$value;

        if ($postId > 0) {
            // https://tommcfarlin.com/wordpress-post-exists-by-id/
            return is_string(get_post_status($postId));
        }

        return true;
    }

}
