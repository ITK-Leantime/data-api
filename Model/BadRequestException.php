<?php

namespace Leantime\Plugins\APIData\Model;

/**
 * A request parameter the caller can fix. The controller turns this into a 400,
 * so the message reaches the client — name the parameter and the expected shape,
 * never the value that was sent.
 */
class BadRequestException extends \InvalidArgumentException
{
}
