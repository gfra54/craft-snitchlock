<?php
/**
 * SnitchLock plugin for Craft CMS 3.x
 *
 * Report when two people might be editing the same entry, category, or global
 *
 */

namespace gfra54\snitchlock\services;

use gfra54\snitchlock\SnitchLock;
use gfra54\snitchlock\models\SnitchLockModel;
use gfra54\snitchlock\records\SnitchLockRecord;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\web\View;

/**
 * Collision Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Marion Newlevant
 * @package   SnitchLock
 * @since     1.0.0
 */
class Collision extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * From any other plugin file, call it like this:
     *
     *     SnitchLock::$plugin->collision->remove()
     *
     * @return mixed
     */
    public function remove(int $snitchlockId, string $snitchlockType, $userId = null)
    {
        $userId = $this->_userId($userId);
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $record = SnitchLockRecord::findOne([
                'snitchlockId' => $snitchlockId,
                'snitchlockType' => $snitchlockType,
                'userId' => $userId
            ]);

            if ($record) {
                $record->delete();
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }


    public function register(int $snitchlockId, string $snitchlockType, $userId = null, \DateTime $now = null)
    {
        $now = $this->_now($now);
        $userId = $this->_userId($userId);
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // look for existing record to update
            $record = SnitchLockRecord::findOne([
                'snitchlockId' => $snitchlockId,
                'snitchlockType' => $snitchlockType,
                'userId' => $userId
            ]);

            if (!$record) {
                $record = new SnitchLockRecord();
                $record->snitchlockId = $snitchlockId;
                $record->snitchlockType = $snitchlockType;
                $record->userId = $userId;
            }
            $record->whenEntered = $now;
            $record->save();

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function getFirstEntered(int $snitchlockId, string $snitchlockType)
    {
        $result = [];
        $rows = SnitchLockRecord::findAll([
            'snitchlockId' => $snitchlockId,
            'snitchlockType' => $snitchlockType,
        ]);
        foreach ($rows as $row)
        {
            $result[] = new SnitchLockModel($row);
        }

        $dates = [];
        foreach( $result as $collision) {
            $dates[$collision['userId']]=date('Y-m-d-H-i-s',$collision['dateCreated']->getTimestamp());
        }
        asort($dates);
        // me($dates,$result);

        $userId = current(array_keys( $dates ));

        return $userId == $this->_userId();
    }

    public function getCollisions(int $snitchlockId, string $snitchlockType, $userId = null)
    {
        $userId = $this->_userId($userId);
        $result = [];
        $rows = SnitchLockRecord::findAll([
            'snitchlockId' => $snitchlockId,
            'snitchlockType' => $snitchlockType,
        ]);
        foreach ($rows as $row)
        {
            if ($row->userId !== $userId)
            {
                $result[] = new SnitchLockModel($row);
            }
        }
        return $result;
    }

    public function expire(\DateTime $now = null)
    {
        $now = $this->_now($now);
        $timeOut = $this->_serverPollInterval() * 10;
        $old = clone $now;
        $old->sub(new \DateInterval('PT'.$timeOut.'S'));
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $allExpired = SnitchLockRecord::find()
                ->where(['<', 'whenEntered', Db::prepareDateForDb($old)])
                ->all();
            foreach ($allExpired as $expired)
            {
                $expired->delete();
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function collidingUsers(array $snitchlockModels)
    {
        $result = [];
        $userIds = [];
        foreach ($snitchlockModels as $model)
        {
            $userIds[] = $model->userId;
        }
        $userIds = array_unique($userIds);
        
        foreach ($userIds as $id)
        {
            $user = Craft::$app->users->getUserById($id);
            if ($user)
            {
                $result[] = $user;
            }
        }
        return $result;
    }

    public function collisionMessages(array $collidingUsers, string $messageTemplate)
    {
        $result = [];

        // save cp template path and set to site templates
        $oldMode = Craft::$app->view->getTemplateMode();
        Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        foreach ($collidingUsers as $user)
        {
            $message = Craft::$app->view->renderString($messageTemplate, ['user' => $user]);
            $result[] = [
                'email' => $user->email,
                'message' => $message,
            ];
        }
        // restore cp template paths
        Craft::$app->view->setTemplateMode($oldMode);

        return $result;
    }

    // ============== default values =============
    private function _userId($userId=false)
    {
        if (!$userId) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($currentUser) {
                $userId = $currentUser->id;
            }
        }
        return (int)($userId);
    }

    private function _now(\DateTime $now = null)
    {
        return ($now ? $now : new \DateTime());
    }

    private function _serverPollInterval()
    {
        $settings = SnitchLock::$plugin->getSettings();
        return $settings['serverPollInterval'];
    }
}
