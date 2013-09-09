<?php

namespace Arara\Process\Ipc;


use ErrorException;

class SharedMemory implements Ipc
{
    private $id = null;
    private $data = array();
    private $ftokFile = null;
    private $size = 0;

    public function save($name, $value)
    {
        $this->data[$name] = $value;

        $data = json_encode($this->data);

        $realSize = strlen($data);
        if($this->size < $realSize) {
            $this->declareMemoryChunk($realSize);
        }

        $bytesWritten = shmop_write($this->id, $data, 0);
        if ($bytesWritten != strlen($data)) {
            $message = 'Could not write the entire length of data';
            throw new \RuntimeException($message);
        }

        return $this;
    }

    public function load($name)
    {
        $loaded = @shmop_read($this->id, 0, $this->size);
        if (false === $loaded) {
            $message = 'Could not read from shared memory block';
            throw new \RuntimeException($message);
        }

        $data = json_decode(trim($loaded), true);
        if (!is_array($data)) {
            $data = array();
        }
        $this->data = $data;

        if (!array_key_exists($name, $this->data)) {
            return null;
        }

        return $this->data[$name];
    }

    public function destroy()
    {
        shmop_delete($this->id);

        return $this;
    }

    public function __destruct()
    {
        shmop_delete($this->id);
        shmop_close($this->id);
        @unlink($this->ftokFile);
    }

    /**
     * @param int $size
     * @throws \RuntimeException
     */
    private function declareMemoryChunk($size = 1024)
    {
        if($this->id !== null) {
            $this->__destruct();
        }

        $id = $this->getFtok();

        $this->id = @shmop_open($id, 'c', 0777, $size);
        if (false === $this->id) {
            $message = 'Could not create shared memory segment';
            throw new \RuntimeException($message);
        }

        $this->size = @shmop_size($this->id);
    }

    /**
     * @return int
     */
    private function getFtok()
    {
        $this->ftokFile = tempnam(sys_get_temp_dir(), "GT_IPC");
        $id = ftok(__FILE__, 't');

        return $id;
    }
}
