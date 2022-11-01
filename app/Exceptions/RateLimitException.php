<?php

class RateLimitException extends Exception {
    protected $message = 'Rate limit exceeded';
}
