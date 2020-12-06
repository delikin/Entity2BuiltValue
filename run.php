<?php
require 'vendor/autoload.php';

//** Evitamos reinventar la rueda y utilizamos un Parser para analizar los archivos */
use PhpParser\ParserFactory;
use PhpParser\{Node, NodeFinder};
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Property;

const RUTA = "C:\\Users\\DELL\\Desktop\\DeliBackend\\src\\Entity";
const RUTA_IMPORT = "import 'package:Enigma/feed/modelos/";
const NULLA = "@nullable".PHP_EOL;
$imports = Array();
$dir = "entidades";

   $archivos = glob(RUTA."\\*.php");
   if(!is_dir($dir)){
       mkdir($dir);
   }

   foreach($archivos as $file){
    $archivo = basename($file);
    $clase = strtolower(substr($archivo, 0, strrpos($archivo, '.')));
    $propiedades = procesarArchivo($file);
    buildImports();
    $data = genClase($clase,$propiedades);
    $data = implode(PHP_EOL,$data);
    file_put_contents($dir."/${clase}.dart",$data);

    //**Limpiamos los imports */
    $imports = Array();
   }

   

   //** Procesamos el archivo y devolvemos las propiedades convertidas a Dart */
   function procesarArchivo($file){
    try {
        $lineas = Array();
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder;
        $variables = Array();
        $ast = $parser->parse(file_get_contents($file));
        $nodeFinder->find($ast, function(Node $node) use(&$variables){
            if($node instanceof Variable && $node->name == "this") return;

            if($node instanceof Property){
              $data = Array();
              $data["nombre"] = $node->props[0]->name->name;
              $data["tipo"] = $node->getDocComment()->jsonSerialize()["text"];
              $variables[] = $data;
            }

          return;
      });


      //** Ya tenemos la data que necesitamos, tenemos el nombre de la variable y la anotacion de tipo */
      foreach ($variables as $v) {
          preg_match("/type\=\"(.+?)\"/",$v["tipo"],$resul);
        if(!empty($resul)){
            $datatipo = toDartType($resul[1]);
            $nm = toCamelCase($v["nombre"]);
            $lineas[]= NULLA.$datatipo." get ".$nm.";";
            continue;
        }

        //Buscamos las relaciones
        preg_match("/targetEntity\=\"App\\\Entity\\\(.+?)\"|targetEntity\=(.+?)::class/",$v["tipo"],$resul);
        if(!empty($resul)){
          $datatipo = !empty($resul[1])?$resul[1] : $resul[2];
          $impo = strtolower($datatipo);

          //echo $datatipo.PHP_EOL;

          //** Verificamos que tipo de relacion tiene */
          preg_match("/@ORM\\\(\w+)/",$v["tipo"],$resul);
          if(!empty($resul)){
              switch ($resul[1]) {
                  case 'OneToMany':
                      $datatipo = "BuiltList<$datatipo>";
                      break;   
                  case 'ManyToMany':
                      $datatipo = "BuiltList<$datatipo>";
                      break;
                  default:
                      break;
              }
          }


          $nm = toCamelCase($v["nombre"]);
          $lineas[]= NULLA.$datatipo." get ".$nm.";";

          //Agregamos el import
          agregarImport($impo);
      }else{
          
      }   
      }

    return $lineas;
    } catch (\Throwable $th) {
        //throw $th;
    }       
   }

   //** Funcion sencilla para convertir a camelCase */
    function toCamelCase($str){
    return lcfirst(str_replace(" ", "", ucwords(strtr($str, "_-", " "))));
   }

   //** Convertimos el tipo de dato en PHP a Dart */
   function toDartType($tipo){
       switch ($tipo) {
           case 'string': return "String";
           case 'text': return "String";
           case 'json': return "String";
           case 'integer': return "int";
           case 'float': return "double";
           case 'datetime': return "DateTime";
           case 'boolean': return "bool";
        default: return $tipo;
       }

   }

   //** Generamos los imports */
   function buildImports(){
       global $imports;
       $imports[]= "import 'package:built_collection/built_collection.dart';";
       $imports[]= "import 'package:built_value/built_value.dart';";
       $imports[]= "import 'package:built_value/serializer.dart';";     
   }

   //** Agregamos un import */
   function agregarImport($archivo){
    global $imports;
    $imports[] = RUTA_IMPORT.$archivo.".dart';";
   }

   /** Generamos la clase completa ( Por lo general es un boilerplate)
    *  Utilizaremos una matriz para tener mayor control sobre la estructura
    */
   function genClase($nombre,$propiedades){
       $low = strtolower($nombre);
       global $imports;
       $clase = Array();
        $clase = array_merge($clase,array_unique($imports));
        $clase[]="\n\n\n\npart '${low}.g.dart';\n\n\n\n";
        $clase[]= "abstract class ${nombre} implements Built<${nombre}, ${nombre}Builder> {";
        $clase = array_merge($clase,array_unique($propiedades));
        $clase[]= "  ${nombre}._();";
        $clase[]= "  factory ${nombre}([void Function(${nombre}Builder) updates]) = _\$${nombre};";
        $clase[]= "  Map<String, dynamic> toJson() {";
        $clase[]= "    return serializers.serializeWith(${nombre}.serializer, this);";
        $clase[]= "  }";
        $clase[]= "  static ${nombre} fromJson(Map<String, dynamic> json) {";
        $clase[]= "    return serializers.deserializeWith(${nombre}.serializer, json);";
        $clase[]= "  }";
        $clase[]= "  static Serializer<${nombre}> get serializer => _\$${low}Serializer;";
        $clase[]= "}";


        return $clase;
        
   }



?>