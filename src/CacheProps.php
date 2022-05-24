<?php

namespace Gasmx;

trait CacheProps
{
    // Loads the state retrieved info from cache into the class
	public function setState($data)
	{
		foreach ($data as $k => $v) {
			$this->{$k} = $v;
		}
	}

	// Magic method invoked when file is included
	public static function __set_state($data)
	{
		$self = new self;
		$self->setState($data);
		return $self;
	}
}