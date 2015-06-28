<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sonata\NotificationBundle\Backend;



use Sonata\NotificationBundle\Exception\BackendNotFoundException;
use Sonata\NotificationBundle\Model\MessageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use ZendDiagnostics\Result\Failure;
use ZendDiagnostics\Result\Success;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy as PheanstalkProxy;

/**
 * Producer side of the rabbitmq backend.
 */
class BeanstalkdBackendDispatcher extends QueueBackendDispatcher
{
    protected $settings;

    protected $beanstalkd;

    protected $default;

    protected $dedicatedTypes = array();


    /**
     * @param array  $settings
     * @param array  $queues
     * @param string $defaultQueue
     * @param array  $backends
     */
    public function __construct($backend, array $queues, $defaultQueue, array $backends)
    {
        parent::__construct($queues, $defaultQueue, $backends);

        $this->settings = array();
        $this->beanstalkd = $backend;

      foreach ($this->queues as $queue) {
            if ($queue['default'] === true) {
                continue;
            }

            $this->dedicatedTypes = array_merge($this->dedicatedTypes, $queue['types']);
        }

        foreach ($this->backends as $backend) {
            if (empty($backend['types'])) {
                $this->default = $backend['backend'];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBackend($type)
    {
        $default = null;

        if (!$type) {
            return $this->getDefaultBackend();
        }

        foreach ($this->backends as $backend) {
            if (in_array($type, $backend['types'])) {
                return $backend['backend'];
            }
        }

        $tube = $this->getDefaultBackend();

        return $tube;
    }

    /**
     * @return BackendInterface
     */
    protected function getDefaultBackend()
    {
        $types = array();
        // $this->default->setTube('default');

        return $this->default;
    }

    /**
     * @return \PhpBeanstalkdLib\beanstalkd\Beanstalkdbeanstalkd
     */
    public function getConnection()
    {

        return $this->beanstalkd;
    }


    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        throw new \RuntimeException('You need to use a specific rabbitmq backend supporting the selected queue to run a consumer.');
    }

    /**
     * {@inheritdoc}
     */
    public function handle(MessageInterface $message, EventDispatcherInterface $dispatcher)
    {
        throw new \RuntimeException('You need to use a specific rabbitmq backend supporting the selected queue to run a consumer.');
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {

        return new Success('beanstalkd is running (RabbitMQ) and consumers for all queues available.');
    }

    /**
     * Calls the rabbitmq management api /api/<vhost>/queues endpoint to list the available queues.
     *
     * @see http://hg.rabbitmq.com/rabbitmq-management/raw-file/3646dee55e02/priv/www-api/help.html
     *
     * @return array
     */
    protected function getApiQueueStatus()
    {
        if (class_exists('Guzzle\Http\Client') === false) {
            throw new \RuntimeException('The guzzle http client library is required to run rabbitmq health checks. Make sure to add guzzle/guzzle to your composer.json.');
        }

        $client = new \Guzzle\Http\Client();
        $client->setConfig(array('curl.options' => array(CURLOPT_CONNECTTIMEOUT_MS => 3000)));
        $request = $client->get(sprintf('%s/queues', $this->settings['console_url']));
        $request->setAuth($this->settings['user'], $this->settings['pass']);

        return json_decode($request->send()->getBody(true), true);
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup()
    {
        throw new \RuntimeException('You need to use a specific rabbitmq backend supporting the selected queue to run a consumer.');
    }


    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
    }
}