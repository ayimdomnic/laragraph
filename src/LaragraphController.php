<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Controllers;

use Illuminate\Routing\Controller as BaseController;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class LaragraphController extends BaseController
{
    /**
     * Handle the incoming request.
     * @return \Illuminate\Http\Response
     */
    public function __invoke()
    {
        // Here you can handle the request and return a response.
        return response()->json([
            'message' => 'Welcome to Laragraph!',
            'status' => 'success',
        ]);
    }
}