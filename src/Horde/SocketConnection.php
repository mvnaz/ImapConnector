<?php

namespace Mvnaz\ImapConnector\Horde;

class SocketConnection extends \Horde_Imap_Client_Socket_Connection_Socket
{

    public function __construct($host, $port = null, $timeout = 30, $secure = false, array $context = array(), array $params = array(), $stream)
    {
        $this->_stream = $stream;
        parent::__construct($host, $port, $timeout, $secure, $context, $params);
    }

    /**
     * Writes data to the IMAP output stream.
     *
     * @param string $data  String data.
     * @param boolean $eol  Append EOL?
     *
     * @throws \Horde_Imap_Client_Exception
     */
    public function write($data, $eol = false)
    {
        if ($eol) {
            $buffer = $this->_buffer;
            $debug = $this->client_debug;
            $this->_buffer = '';

            $this->client_debug = true;

            if (@fwrite($this->_stream, $buffer . $data . ($eol ? "\r\n" : '')) === false) {
                throw new \Horde_Imap_Client_Exception(
                    \Horde_Imap_Client_Translation::r("Server write error."),
                    \Horde_Imap_Client_Exception::SERVER_WRITEERROR
                );
            }

            if ($debug) {
                $this->_params['debug']->client($buffer . $data);
            }
        } else {
            $this->_buffer .= $data;
        }
    }


    protected function _connect($host, $port, $timeout, $secure, $context, $retries = 0)
    {
        $conn = '';
        if (!strpos($host, '://')) {
            switch (strval($secure)) {
                case 'ssl':
                case 'sslv2':
                case 'sslv3':
                    $conn = $secure . '://';
                    $this->_secure = true;
                    break;

                case 'tlsv1':
                    $conn = 'tls://';
                    $this->_secure = true;
                    break;

                case 'tls':
                default:
                    $conn = 'tcp://';
                    break;
            }
        }
        $conn .= $host;
        if ($port) {
            $conn .= ':' . $port;
        }


        if ($this->_stream === false) {
            /* From stream_socket_client() page: a function return of false,
             * with an error code of 0, indicates a "problem initializing the
             * socket". These kind of issues are seen on the same server
             * (and even the same user account) as sucessful connections, so
             * these are likely transient issues. Retry up to 3 times in these
             * instances. */
//            if (!$error_number && ($retries < 3)) {
//                return $this->_connect($host, $port, $timeout, $secure, $context, ++$retries);
//            }

            $e = new \Horde\Socket\Client\Exception(
                'Error connecting to server.'
            );
//            $e->details = sprintf("[%u] %s", $error_number, $error_string);
            throw $e;
        }

        stream_set_timeout($this->_stream, $timeout);

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->_stream, 0);
        }
        stream_set_write_buffer($this->_stream, 0);

        $this->_connected = true;
    }


}