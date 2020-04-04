<?php

/**
 * A straightforward implementation of an in-memory key-value store.
 */
class naju_kvs
{
    private const MAX_STORE_SIZE = 10000;

    /**
     * Fetches the value associated with `$key` from the store.
     * 
     * If no value is associated with the key, `null` will be returned.
     *
     * @param string $key
     * @return object|null
     */
    public static function get($key)
    {
        return self::$store[$key] ?? null;
    }

    /**
     * Stores a new value. In case some other object was already associated
     * with `$key`, it will be returned or `null` otherwise.
     * 
     * There are no guarantees on how long an item will stay in the store. In case
     * of a potential store overflow, random items will be dropped.
     *
     * @param string $key
     * @param object $val
     * @return object|null
     */
    public static function put($key, $val)
    {
        self::preventOverflow();

        $previous_val = self::get($key);
        self::$store[$key] = $val;

        self::markStoreDirty();
        return $previous_val;
    }

    /**
     * Deletes an entry from the store. If `$key` was in use, the
     * associated object will be returned or `null` otherwise.
     *
     * @param string $key
     * @return object|null
     */
    public static function invalidate($key)
    {
        $previous_val = self::get($key);

        if ($previous_val) {
            unset(self::$store[$key]);
        }

        self::markStoreDirty();
        return $previous_val;
    }

    /**
     * Rebuilds the store from the addon cache. 
     */
    static function init()
    {
        if (self::$initialized) {
            return;
        }

        $serialized_store = rex_file::get(rex_path::addonCache(naju_rex_extensions::ADDON_NAME, 'kvs'));
        self::$store = unserialize($serialized_store);

        self::$initialized = true;
    }

    /**
     * Serializes the store and writes it to the addon cache.
     */
    static function persist()
    {
        // Theoretically, writing the store may create a race-conflict when
        // two independent sessions terminate right at the same time.
        // However, as both handlers would write to the same file, it is up
        // to the OS to handle the write-queueu and decide which goes first.
        // Therefore we pushed the conflict several layers down.
        // The only problem that remains is that we will suffer a lost update
        // in this case. But this not a problem either because we are only
        // building a cache and the lost values were just there for speedup
        // reasons and will be re-populated eventually.

        $serialized_store = serialize(self::$store);
        rex_file::put(rex_path::addonCache(naju_rex_extensions::ADDON_NAME, 'kvs'), $serialized_store);
    }

    /**
     * Schedules processes to retain the new store state after the session finished.
     */
    private static function markStoreDirty()
    {
        if (!self::$first_mod) {
            return;
        }

        register_shutdown_function(array('naju_kvs', 'persist'));

        self::$first_mod = false;
    }

    /**
     * Drops random entries from the store until MAX_STORE_SIZE is no longer reached.
     */
    private static function preventOverflow()
    {
        while (sizeof(self::$store) >= self::MAX_STORE_SIZE) {
            $key_to_drop = array_rand(self::$store);
            unset(self::$store[$key_to_drop]);
        }
    }

    private static $store = array();
    private static $initialized = false;
    private static $first_mod = true;

}

naju_kvs::init();
