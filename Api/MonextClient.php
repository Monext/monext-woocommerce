<?php

namespace Payline\Api;

//use Api\ApiInterface;
use GuzzleHttp\BodySummarizer;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Monolog\Handler\StreamHandler;
use WC_Log_Handler_File;
use Monolog\Logger;

class MonextClient implements ApiInterface
{
    static $baseUrl = '';

    static $authorization = '';

    static $version = 'WooCommerce';
    protected $loggerPath;

    public static function isConfigured(): bool
    {
        return '' !== self::$baseUrl && '' !== self::$authorization;
    }

    public static function configure(string $baseUrl, string $authorization): void
    {
        self::$baseUrl = $baseUrl;
        self::$authorization = $authorization;
    }

    public static function usedBy(string $version)
    {
        self::$version = $version;
    }

    /**
     * @return array<mixed>
     */
    public function getSession(string $sessionId): array
    {
        // Appel spécial : utilise BodySummarizer en cas d'erreur
        return $this->callApi('GET',
            sprintf('%s/%s', self::SESSIONS_ENDPOINT, $sessionId),
            null,
            true,
            __FUNCTION__);
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    public function createSession(array $payload): array
    {
        return $this->callApi('POST',
            self::SESSIONS_ENDPOINT,
            $payload,
            false,
            __FUNCTION__);
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    public function captureTransaction(string $transactionId, array $payload): array
    {
        return $this->callApi('POST',
            sprintf(self::TRANSACTIONS_CAPTURE_ENDPOINT, $transactionId),
            $payload,
            false,
            __FUNCTION__);
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    public function refundTransaction(string $transactionId, array $payload): array
    {
        return $this->callApi('POST',
            sprintf(self::TRANSACTIONS_REFUND_ENDPOINT, $transactionId),
            $payload,
            false,
            __FUNCTION__);
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    public function cancelTransaction(string $transactionId, array $payload): array
    {
        return $this->callApi('POST',
            sprintf(self::TRANSACTIONS_CANCEL_ENDPOINT, $transactionId),
            $payload,
            false,
            __FUNCTION__);
    }

    /**
     * @return array<mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->callApi('GET',
            sprintf('%s/%s', self::TRANSACTIONS_ENDPOINT, $transactionId),
            null,
            false,
            __FUNCTION__);
    }

    public function getEncryptionKey($name)
    {
        return $this->callApi('GET',
            sprintf('%s/%s', self::SECURITY_ENCRYPTION_ENDPOINT, $name),
            null,
            false,
            __FUNCTION__);
    }

    public function getPointsOfSale(string $merchantId)
    {
        return $this->callApi('GET',
            sprintf(self::MERCHANTS_POS_ENDPOINT, $merchantId),
            null,
            false,
            __FUNCTION__);
    }

    public function getPointOfSale(string $merchantId, string $posReference)
    {
        return $this->callApi('GET',
            sprintf(self::MERCHANTS_POS_ENDPOINT . '/%s', $merchantId, $posReference),
            null,
            false,
            __FUNCTION__);
    }

    public function getPaymentMethods(string $merchantId, string $posReference)
    {
        return $this->callApi('GET',
            sprintf(self::MERCHANTS_PAYMENT_METHOD_ENDPOINT, $merchantId, $posReference),
            null,
            false,
            __FUNCTION__);
    }

    public function getPaymentMethod(string $merchantId, string $posReference, string $paymentMethodId)
    {
        return $this->callApi('GET',
            sprintf(self::MERCHANTS_PAYMENT_METHOD_ENDPOINT . '/%s', $merchantId, $posReference, $paymentMethodId),
            null,
            false,
            __FUNCTION__);
    }

    public function createMXWalletSession(array $payload)
    {
        return $this->callApi('POST',
            self::MXWALLET_SESSIONS_ENDPOINT,
            $payload,
            false,
            __FUNCTION__);
    }

    private function getOptionatedClient(): GuzzleHttpClient
    {
        // Client must be configured at this point !
        if (!static::isConfigured()) {
            throw new \RuntimeException('Client must be configured at this point !');
        }

        return new GuzzleHttpClient(
            [
                'base_uri' => self::$baseUrl,
                'headers' => [
                    'Authorization' => 'Basic '.self::$authorization,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Version' => self::$version
                ],
            ]
        );
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private static function getBody(array $payload): array
    {
        return [
            'body' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Effectue un appel HTTP vers l'API Monext.
     *
     * @param string $httpMethod 'GET' ou 'POST' (case-insensitive)
     * @param string $uri URI relative (ex. self::SESSIONS_ENDPOINT)
     * @param array|null $payload Corps de la requête (pour POST) ou null
     * @param bool $useSpecialGetSession true uniquement pour getSession; active le BodySummarizer
     * @param string $callerMethod Nom de la méthode qui appelle callApi (pour le logging)
     *
     * @return array Décodage JSON de la réponse ou tableau d'erreur
     * @throws GuzzleException
     */
    protected function callApi(string $httpMethod, string $uri, ?array $payload = null, bool $useSpecialGetSession = false, string $callerMethod = ''): array
    {
        try {
            $client = $this->getOptionatedClient();

            $options = $payload !== null ? self::getBody($payload) : [];
            $this->getLogger()->info($callerMethod . 'Request', $options);

            $method = strtoupper($httpMethod);
            switch ($method) {
                case 'GET':
                    $response = $client->get($uri, $options);
                    break;
                case 'POST':
                    $response = $client->post($uri, $options);
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported HTTP method: {$httpMethod}");
            }

            $result = json_decode((string) $response->getBody(), true);
            $this->getLogger()->info($callerMethod . 'Response', $result);

            if (200 === $response->getStatusCode() && isset($result['title']) && 'Unauthorized' === $result['title']) {
                throw new \Exception((string) $response->getBody());
            }

            return $result;
        } catch (\Throwable $e) {
            // Traitement spécial pour getSession : on veut résumer le corps de la réponse lorsqu'il existe
            if ($useSpecialGetSession && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                $summarized = (new BodySummarizer())->summarize($e->getResponse());
                return json_decode((string) $summarized, true);
            }

            return $this->handleException($e, $callerMethod);
        }
    }

    /**
     * @return array<mixed>
     */
    protected function handleServerException(ServerException $exception): array
    {
        return array_merge(
            [
                'error' => $exception->getMessage(),
                'statusCode' => $exception->getCode(),
                'result' => [
                    'error' => $exception->getMessage(),
                    'statusCode' => $exception->getCode(),
                    'code' => 'ERRORxxx',
                    'detail' => 'DETAILxxx'
                ]
            ], json_decode($exception->getResponse()->getBody()->getContents(), true)
        );
    }

    /**
     * @return array<mixed>
     */
    protected function handleException(\Throwable $exception, $callerMethod): array
    {
        $this->getLogger()->error('Exception occured at ' . $callerMethod . ' call', array(
            'code'     => $exception->getCode(),
            'message'  => $exception->getMessage(),
            'endpoint' => $callerMethod
        ));

        if ($exception instanceof ServerException) {
            return $this->handleServerException($exception);
        }



        $result =  json_decode($exception->getMessage(), true); //Usefull for admin error messages
        if(empty($result)) {
            $result = [
                'error' => true,
                'statusCode' => 500,
                'code' => 'ERRORxxx',
                'title' => 'ERROR',
                'detail' => 'DETAILxxx'
            ];
        }

        return [
            'error' => true,
            'statusCode' => 500,
            'title' => 'ERROR',
            'detail' => $exception->getMessage(),
            //'result' => json_decode($exception->getMessage(), true) //Usefull for admin error messages
            'result' => $result
            ];
    }

    public function getLogger()
    {
        if (!isset($this->logger)) {
            $this->logger = new Logger('PaylineSDK');
        }

        $logfileDate = (new \DateTime('now', new \DateTimeZone("Europe/Paris")))->format('Y-m-d');
        $pathLog = trailingslashit( dirname( WC_Log_Handler_File::get_log_file_path( 'payline' ) ) ) . trailingslashit( 'payline' );
        $this->loggerPath = $pathLog . $logfileDate . '.log';

        try {
            if(is_writable($pathLog) || is_writable(dirname($pathLog))) {
                $this->logger->pushHandler(new StreamHandler($this->loggerPath, \Monolog\Logger::INFO)); // set default log folder
            }
        } catch (\Exception $e) {
            $this->loggerPath = null;
            //No logger can be used
        }

        return $this->logger;
    }
}