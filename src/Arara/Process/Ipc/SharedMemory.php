<?php

namespace Arara\Process\Ipc;


use ErrorException;

class SharedMemory implements Ipc
{
    private $id = null;
    private $data = array();
    private static $number = 1;
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
        var_dump('DEBUG: delete and close mem_block '.$this->id);
        var_dump("DEBUG: ". shmop_delete($this->id) ? 'true' : 'false');
        shmop_close($this->id);

        var_dump('DEBUG: delete file '.$this->ftokFile);
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
        } else {
            var_dump('DEBUG: create mem_block '.$this->id);
        }

        $this->size = @shmop_size($this->id);
    }

    /**
     * @return int
     */
    private function getFtok()
    {
        $this->ftokFile = tempnam(sys_get_temp_dir(), "GT_IPC".self::$number);
        var_dump('DEBUG: create file'.$this->ftokFile);
        $id = ftok(__FILE__, 't');

        self::$number++;
        return $id;
    }
}
