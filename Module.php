<?php

namespace filsh\yii2\oauth2server;

use \Yii;
use yii\helpers\ArrayHelper;
use yii\i18n\PhpMessageSource;

/**
 * For example,
 * 
 * ```php
 * 'oauth2' => [
 *     'class' => 'filsh\yii2\oauth2server\Module',
 *     'tokenParamName' => 'accessToken',
 *     'tokenAccessLifetime' => 3600 * 24,
 *     'storageMap' => [
 *         'user_credentials' => 'common\models\User',
 *     ],
 *     'grantTypes' => [
 *         'user_credentials' => [
 *             'class' => 'OAuth2\GrantType\UserCredentials',
 *         ],
 *         'refresh_token' => [
 *             'class' => 'OAuth2\GrantType\RefreshToken',
 *             'always_issue_new_refresh_token' => true
 *         ]
 *     ]
 * ]
 * ```
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    use BootstrapTrait;
    
    const VERSION = '2.0.0';
    
    /**
     * @var array Model's map
     */
    public $modelMap = [];
    
    /**
     * @var array Storage's map
     */
    public $storageMap = [];
    
    /**
     * @var array GrantTypes collection
     */
    public $grantTypes = [];

    /**
     * @var array ResponseTypes collection
     */
    public $responseTypes = [];
    
    /**
     * @var string Name of access token parameter
     */
    public $tokenParamName;
    
    /**
     * @var integer Max access token lifetime in seconds
     */
    public $tokenAccessLifetime;
    
    /**
     * @var integer Max refresh token lifetime in seconds
     */
    public $tokenRefreshLifetime;
    
    /**
     * @var array additional server configuration
     */
    public $serverConfig = [];

    /**
     * @var bool enforce state flag
     */
    public $enforceState;

    /**
     * @var bool allow_implicit flag
     */
    public $allowImplicit;
    
    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        $this->initModule($this);
        
        if ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'filsh\yii2\oauth2server\commands';
        }
    }
    
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->registerTranslations();
    }
    
    /**
     * Gets Oauth2 Server
     * 
     * @return \filsh\yii2\oauth2server\Server
     * @throws \yii\base\InvalidConfigException
     */
    public function getServer()
    {
        if(!$this->has('server')) {
            $storages = [];
            foreach(array_keys($this->storageMap) as $name) {
                $storages[$name] = \Yii::$container->get($name);
            }
            
            $grantTypes = [];
            foreach($this->grantTypes as $name => $options) {
                if(!isset($storages[$name]) || empty($options['class'])) {
                    throw new \yii\base\InvalidConfigException('Invalid grant types configuration.');
                }

                $class = $options['class'];
                unset($options['class']);

                $reflection = new \ReflectionClass($class);
                $config = array_merge([0 => $storages[$name]], [$options]);

                $instance = $reflection->newInstanceArgs($config);
                $grantTypes[$name] = $instance;
            }
            
            $serverConfig = ArrayHelper::merge($this->serverConfig, [
                 'token_param_name' => $this->tokenParamName,
                 'access_lifetime' => $this->tokenAccessLifetime,
                 'refresh_token_lifetime' => $this->tokenRefreshLifetime,
             ]);
            
            $server = \Yii::$container->get(Server::className(), [
                $this,
                $storages,
                $serverConfig,
                $grantTypes,
                $this->responseTypes
            ]);

            $this->set('server', $server);
        }
        return $this->get('server');
    }
    
    public function getRequest()
    {
        if(!$this->has('request')) {
            $this->set('request', Request::createFromGlobals());
        }
        return $this->get('request');
    }
    
    public function getResponse()
    {
        if(!$this->has('response')) {
            $this->set('response', new Response());
        }
        return $this->get('response');
    }

    /**
     * @param $response
     */
    public function setResponse($response)
    {
        Yii::$app->response->setStatusCode($response->getStatusCode());
        $headers = Yii::$app->response->getHeaders();

        foreach ($response->getHttpHeaders() as $name => $value) {
            $headers->set($name, $value);
        }
    }

    /**
     * @param $isAuthorized
     * @param $userId
     * @return \OAuth2\ResponseInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function handleAuthorizeRequest($isAuthorized, $userId)
    {
        $response = $this->getServer()->handleAuthorizeRequest(
            $this->getRequest(),
            $this->getResponse(),
            $isAuthorized,
            $userId
        );
        $this->setResponse($response);

        return $response;
    }

    /**
     * Register translations for this module
     * 
     * @return array
     */
    public function registerTranslations()
    {
        if(!isset(Yii::$app->get('i18n')->translations['modules/oauth2/*'])) {
            Yii::$app->get('i18n')->translations['modules/oauth2/*'] = [
                'class'    => PhpMessageSource::className(),
                'basePath' => __DIR__ . '/messages',
            ];
        }
    }
    
    /**
     * Translate module message
     * 
     * @param string $category
     * @param string $message
     * @param array $params
     * @param string $language
     * @return string
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        return Yii::t('modules/oauth2/' . $category, $message, $params, $language);
    }
}
