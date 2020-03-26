<?php

namespace Alchemy\ExposePlugin\Security;

use Alchemy\Phrasea\Authorization\BaseVoter;
use Alchemy\Phrasea\Model\Entities\User;
use Alchemy\Phrasea\Model\Repositories\UserRepository;

class PluginVoter extends BaseVoter
{
    const VIEW = 'view';

    private $configuration;

    public function __construct(UserRepository $userRepository, $configuration)
    {
        parent::__construct(
            $userRepository,
            [self::VIEW],
            [
                'Alchemy\ExposePlugin\ActionBar\ActionBarPlugin',
            ]
        );

        $this->configuration  = $configuration;
    }


    protected function isGranted($attribute,
        /** @noinspection PhpUnusedParameterInspection */
                                 $plugin,
        /** @noinspection PhpUnusedParameterInspection */
                                 User $user = null)
    {
        switch ($attribute) {
            case self::VIEW:
                if ($this->configuration) {
                    return true;
                }

                return false;
        }

        return false;
    }
}
