<?php

declare(strict_types=1);

namespace HighestDreams\AdvancedNewsPaper;

class News
{

    protected static $main;

    public function __construct(Main $main)
    {
        self::$main = $main;
    }

    public static function getData()
    {
        $get = function (string $parameter) {
            return !is_bool($Param = Main::$Settings->get($parameter)) ? Main::$Settings->get($parameter) : trigger_error('Please Stop and Start your server until fix this error...');
        };
        return json_decode(file_get_contents("https://newsapi.org/v2/top-headlines?category={$get('category')}&country={$get('country')}&language={$get('language')}&pageSize=10&apiKey=45110ab1a3304c1e9a35d06173075d2f"));
    }

    public static function save()
    {
        Main::$Database->setAll([self::getData()]);
        Main::$Database->save();
    }

    public static function isSet(): bool
    {
        return !empty(Main::$Database->getAll());
    }

    public static function isOk(): bool
    {
        return Main::$Database->get(0)->status === "ok";
    }

    public static function get(int $post)
    {
        return (self::isSet() and self::isOk()) ? Main::$Database->get(0)->articles[$post] : self::save();
    }

    /**
     * @param int $post
     * @return string
     */
    public static function getTitle(int $post)
    {
        return self::get($post)->title;
    }

    /**
     * @param int $post
     * @return
     */
    public static function getImage(int $post)
    {
        return self::get($post)->urlToImage;
    }

    /**
     * @param int $post
     * @return mixed
     */
    public static function getContent(int $post)
    {
        return self::get($post)->content ?? self::get($post)->description;
    }

    /**
     * @param int $post
     * @return string
     */
    public static function getSource(int $post)
    {
        return self::get($post)->source->name ?? "Unknown";
    }

    /**
     * @param int $post
     * @param string $Detail
     * @return mixed|string
     */
    public static function getDetail(int $post, string $Detail)
    {
        $date = explode('T', self::get($post)->publishedAt);
        if ($Detail === 'date') {
            return $date[0];
        } elseif ($Detail === 'time') {
            $time = explode('Z', $date[1]);
            return $time[0];
        }
    }

    /**
     * @param int $post
     * @return string
     */
    public static function readMore(int $post)
    {
        $parsed = parse_url(self::get($post)->url);
        return $parsed['host'] . $parsed['path'];
    }

    /**
     * @param int $post
     * @return bool
     */
    public static function isImageEmpty (int $post): bool {
        return empty(self::getImage($post));
    }
}