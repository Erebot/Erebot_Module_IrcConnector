<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

class   Erebot_Module_IrcConnector
extends Erebot_Module_Base
{
    protected $_password;
    protected $_nickname;
    protected $_identity;
    protected $_hostname;
    protected $_realname;

    public function reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $handler = new Erebot_EventHandler(
                array($this, 'handleConnect'),
                new Erebot_Event_Match_InstanceOf('Erebot_Event_Logon')
            );
            $this->_connection->addEventHandler($handler);
        }
    }

    protected function sendCredentials()
    {
        $this->_password = $this->parseString('password', '');
        $this->_nickname = $this->parseString('nickname');
        $this->_identity = $this->parseString('identity', 'Erebot');
        $this->_hostname = $this->parseString('hostname', 'Erebot');
        $this->_realname = $this->parseString('realname', 'Erebot');

        $config =&  $this->_connection->getConfig(NULL);
        $url    =   parse_url($config->getConnectionURL());

        if ($this->_password != '')
            $this->sendCommand('PASS '.$this->_password);
        $this->sendCommand('NICK '.$this->_nickname);
        $this->sendCommand('USER '.$this->_identity.' '.$this->_hostname.
                            ' '.$url['host'].' :'.$this->_realname);
    }

    public function handleConnect(Erebot_Interface_Event_Generic &$event)
    {
        $config =&  $this->_connection->getConfig(NULL);
        $url    =   parse_url($config->getConnectionURL());

        // If no upgrade should be performed or
        // if the connection is already encrypted.
        if (!$this->parseBool('upgrade', FALSE) ||
            !strcasecmp($url['scheme'], 'ircs'))
            $this->sendCredentials();
        // Otherwise, start a TLS negociation.
        else {
            $handler = new Erebot_RawHandler(
                array($this, 'handleSTARTTLSSuccess'),
                Erebot_Interface_Event_Raw::RPL_STARTTLSOK
            );
            $this->_connection->addRawHandler($handler);
            $handler = new Erebot_RawHandler(
                array($this, 'handleSTARTTLSFailure'),
                Erebot_Interface_Event_Raw::ERR_STARTTLSFAIL
            );
            $this->_connection->addRawHandler($handler);
            $this->sendCommand('STARTTLS');
        }
    }

    public function handleSTARTTLSSuccess(Erebot_Interface_Event_Raw &$raw)
    {
        try {
            stream_socket_enable_crypto(
                $this->_connection->getSocket(),
                TRUE,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );
        }
        catch (Erebot_ErrorReportingException $e) {
            $this->_connection->disconnect(NULL, TRUE);
        }
        $this->sendCredentials();
    }

    public function handleSTARTTLSFailure(Erebot_Interface_Event_Raw &$raw)
    {
        $this->_connection->disconnect(NULL, TRUE);
    }

    public function getNetPassword()
    {
        return $this->_password;
    }

    public function getBotNickname()
    {
        return $this->_nickname;
    }

    public function getBotIdentity()
    {
        return $this->_identity;
    }

    public function getBotHostname()
    {
        return $this->_hostname;
    }

    public function getBotRealname()
    {
        return $this->_realname;
    }
}

