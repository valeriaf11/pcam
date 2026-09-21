IG_PATH define la ruta ABSOLUTA DEL ARCHIVO DE CONFIGURACION



class Config
{
    static function load()
    {
        $xml = simplexml_load_file(CONFIG_PATH . '/config.xml');
        