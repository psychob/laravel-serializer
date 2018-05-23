<?php

namespace Atrauzzi\LaravelSerializer\MetaData;

use Atrauzzi\LaravelSerializer\JMSSerializerServiceProvider;
use JMS\Serializer\Metadata\ClassMetadata;

class ClassData extends ClassMetadata
{
    protected $version;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->createdAt = filemtime(config_path('serializer.php'));
        $this->fileResources[] = config_path('serializer.php');

        $this->version = JMSSerializerServiceProvider::VERSION;
    }

    public function isFresh($timestamp = null)
    {
        return parent::isFresh($timestamp) && $this->isCurrentVersionCache();
    }

    private function isCurrentVersionCache(): bool
    {
        // we only check version when debug is enabled
        if (!config('app.debug', false)) {
            return true;
        }

        return version_compare($this->version, JMSSerializerServiceProvider::VERSION, '<=');
    }

    public function serialize()
    {
        return serialize([
            $this->version,
            parent::serialize(),
        ]);
    }

    public function unserialize($str)
    {
        list($this->version, $parent) = unserialize($str);

        parent::unserialize($parent);
    }
}