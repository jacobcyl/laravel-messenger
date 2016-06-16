<?php
namespace Jacobcyl\Messenger;

use Illuminate\Support\Facades\Facade;

class MessengerFacade extends Facade{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'messenger';
    }

}