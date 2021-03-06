<?php

//--------------------------------------------------------------------------
// Application Error Logger
//--------------------------------------------------------------------------

Log::useFiles(storage_path() .'Logs' .DS .'error.log');

//--------------------------------------------------------------------------
// Application Error Handler
//--------------------------------------------------------------------------

use Exception\RedirectToException;

App::error(function(Exception $exception, $code)
{
    // Do not log the Redirect Exceptions.
    if (! $exception instanceof RedirectToException) {
        Log::error($exception);
    }
});

//--------------------------------------------------------------------------
// Application Missing Route Handler
//--------------------------------------------------------------------------

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

App::missing(function(NotFoundHttpException $exception)
{
    $status = $exception->getStatusCode();

    $headers = $exception->getHeaders();

    if (Request::ajax()) {
        // An AJAX request; we'll create a JSON Response.
        $content = array('status' => $status);

        // Setup propely the Content Type.
        $headers['Content-Type'] = 'application/json';

        return Response::json($content, $status, $headers);
    }

    // We'll create the templated Error Page Response.
    $response = Template::make('default')
        ->shares('title', 'Error ' .$status)
        ->nest('content', 'Error/' .$status);

    // Setup propely the Content Type.
    $headers['Content-Type'] = 'text/html';

    return Response::make($response->render(), $status, $headers);
});

//--------------------------------------------------------------------------
// Try To Register Again The Config Manager
//--------------------------------------------------------------------------

use Config\Repository as ConfigRepository;
use Support\Facades\Facade;

if(CONFIG_STORE == 'database') {
    // Get the Database Connection instance.
    $connection = $app['db']->connection();

    // Get a fresh Config Loader instance.
    $loader = $app->getConfigLoader();

    // Setup Database Connection instance.
    $loader->setConnection($connection);

    // Refresh the Application's Config instance.
    $app->instance('config', $config = new ConfigRepository($loader));

    // Make the Facade to refresh its information.
    Facade::clearResolvedInstance('config');
} else if(CONFIG_STORE != 'files') {
    throw new \InvalidArgumentException('Invalid Config Store type.');
}

//--------------------------------------------------------------------------
// Start the Legacy Session
//--------------------------------------------------------------------------

use Helpers\Session as LegacySession;

LegacySession::init();

//--------------------------------------------------------------------------
// Boot Stage Customization
//--------------------------------------------------------------------------

/**
 * Create a constant for the URL of the site.
 */
define('SITEURL', $app['config']['app.url']);

/**
 * Define relative base path.
 */
define('DIR', $app['config']['app.path']);

/**
 * Create a constant for the name of the site.
 */
define('SITETITLE', $app['config']['app.name']);

/**
 * Set a default language.
 */
define('LANGUAGE_CODE', $app['config']['app.locale']);

/**
 * Set the default template.
 */
define('TEMPLATE', $app['config']['app.template']);

/**
 * Set a Site administrator email address.
 */
define('SITEEMAIL', $app['config']['app.email']);

/**
 * Send a E-Mail to administrator (defined on SITEEMAIL) when a Error is logged.
 */
/*
use Shared\Log\Mailer as LogMailer;

LogMailer::initHandler($app);
*/
