<?php
/**
 * SnitchLock plugin for Craft CMS 3.x
 *
 * Lock entry when two people might be editing the same entry, category, or global
 *
 */

namespace gfra54\snitchlock\controllers;

use gfra54\snitchlock\SnitchLock;

use Craft;
use craft\web\Controller;

/**
 * Collision Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Marion Newlevant
 * @package   SnitchLock
 * @since     1.0.0
 */
class CollisionController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['ajax-enter', 'get-config'];

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's actionAjaxEnter URL,
     * e.g.: actions/snitchlock/collision/ajax-enter
     *
     * Called from the javascript regularly (every 2 seconds)
     * to report that the thing is indeed being edited.
     *
     * @return mixed
     */
    public function actionAjaxEnter()
    {
        $this->requireAcceptsJson();

        // require login (gracefully)
        $userSession = Craft::$app->getUser();
        if ($userSession->getIsGuest()) {
            $json = $this->asJson([
                'success' => false,
                'error' => 'not logged in',
            ]);
            return $json;
        }

        $snitchlockId = (int)(Craft::$app->getRequest()->getBodyParam('snitchlockId'));
        $snitchlockType = Craft::$app->getRequest()->getBodyParam('snitchlockType');
        $messageTemplate = Craft::$app->getRequest()->getBodyParam('messageTemplate');
        // expire any old collisions
        SnitchLock::$plugin->collision->expire();
        // record this person is editing this element
        SnitchLock::$plugin->collision->register($snitchlockId, $snitchlockType);
        // get any collisions
        $collisionModels = SnitchLock::$plugin->collision->getCollisions($snitchlockId, $snitchlockType);
        $firstEntered = SnitchLock::$plugin->collision->getFirstEntered($snitchlockId, $snitchlockType);

        if(!$firstEntered) {
            // pull the users out of our collisions
            $collidingUsers = SnitchLock::$plugin->collision->collidingUsers($collisionModels);
            $collisionMessages = SnitchLock::$plugin->collision->collisionMessages($collidingUsers, $messageTemplate);
        } else {
            $collisionMessages='';
        }
        // and return
        $json = $this->asJson([
            'success' => true,
            'collisions' => $collisionMessages,
            'firstEntered'=>$firstEntered
        ]);
        return $json;
    }

    /**
     * Handle a request going to our plugin's actionGetConfig URL,
     * e.g.: actions/snitchlock/collision/get-config
     *
     * @return mixed
     */
    public function actionGetConfig()
    {
        $this->requireAcceptsJson();
        $settings = SnitchLock::$plugin->getSettings();
        $json = $this->asJson([
            'messageTemplate' => $settings['messageTemplate'],
            'serverPollInterval' => $settings['serverPollInterval'],
            'elementInputIdSelector' => $settings['elementInputIdSelector'],
            'fieldInputIdSelector' => $settings['fieldInputIdSelector'],
        ]);
        return $json;
    }
}
