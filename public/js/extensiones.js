/* =============================================================================
   extensiones.js  -  Qué ícono mostrar según la extensión del archivo
   =============================================================================
   Cada extensión apunta a un archivo de public/img/tipos/<tipo>.svg
   Para agregar una extensión nueva basta con añadirla a la lista del tipo.
   Si una extensión no está aquí se usa "generic".
   (Se conserva la idea original: docx->doc, xlsx/csv->xls, ppt*->pps.)
   ============================================================================= */
var lista_extensiones = {
    doc:   ['doc', 'docx', 'odt', 'rtf', 'dot', 'dotx'],
    xls:   ['xls', 'xlsx', 'xlsm', 'csv', 'ods'],
    pps:   ['ppt', 'pptx', 'pps', 'ppsx', 'odp'],
    pdf:   ['pdf'],
    img:   ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg', 'tif', 'tiff', 'ico'],
    zip:   ['zip', 'rar', '7z', 'tar', 'gz'],
    txt:   ['txt', 'log', 'md', 'ini', 'cfg'],
    audio: ['mp3', 'wav', 'ogg', 'm4a', 'wma'],
    video: ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'webm'],
    code:  ['html', 'htm', 'css', 'js', 'json', 'xml', 'sql'],
    dwg:   ['dwg', 'dxf', 'vsd', 'vsdx']
};

/** Regresa el nombre del ícono (sin .svg) para una extensión */
function tipoPorExtension(ext) {
    ext = (ext || '').toLowerCase();
    for (var tipo in lista_extensiones) {
        if (lista_extensiones[tipo].indexOf(ext) !== -1) {
            return tipo;
        }
    }
    return 'generic';
}
