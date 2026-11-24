<?php
header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';
require_once $rutas['autoload'];
try { Dotenv\Dotenv::createImmutable($rutas['env_api'])->safeLoad(); } catch (Throwable $e) {}
require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

function vacJson(string $status, string $msg, $data = null): void {
    $out = ['status'=>$status,'msg'=>$msg];
    if ($data !== null) $out['data']=$data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}
function vacRegla(mysqli $db, int $antiguedad): ?array {
    $st=$db->prepare("SELECT idregla,dias_asignados FROM rrhh_vacaciones_reglas
        WHERE activo=1 AND antiguedad_desde<=?
          AND (antiguedad_hasta IS NULL OR antiguedad_hasta>=?)
        ORDER BY antiguedad_desde DESC LIMIT 1");
    $st->bind_param('ii',$antiguedad,$antiguedad);
    $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
    return $r ?: null;
}
function vacAntiguedad(mysqli $db, string $fechaInicio, int $anio): int {
    if (!$fechaInicio) return 0;
    $st=$db->prepare("SELECT GREATEST(TIMESTAMPDIFF(YEAR,?,CONCAT(?,'-12-31')),0) antiguedad");
    $st->bind_param('si',$fechaInicio,$anio); $st->execute();
    $r=(int)$st->get_result()->fetch_assoc()['antiguedad']; $st->close(); return $r;
}
function vacDiasHabiles(string $desde, string $hasta): int {
    $a=new DateTimeImmutable($desde); $b=new DateTimeImmutable($hasta);
    if($b<$a) throw new RuntimeException('La fecha hasta no puede ser anterior a la fecha desde.');
    $dias=0;
    for($d=$a;$d<=$b;$d=$d->modify('+1 day')) if((int)$d->format('N')<=5) $dias++;
    return $dias;
}
function vacSaldo(mysqli $db, int $idempleado, int $anio, int $base, int $ajuste): array {
    // Asignación y ajuste base viven en saldos. Movimientos contabilizan consumos,
    // reintegros y vencimientos; el movimiento de asignación es trazabilidad.
    $st=$db->prepare("SELECT
        COALESCE(SUM(CASE WHEN tipo='consumo' THEN cantidad_dias ELSE 0 END),0) consumidos,
        COALESCE(SUM(CASE WHEN tipo='reintegro' THEN cantidad_dias ELSE 0 END),0) reintegrados,
        COALESCE(SUM(CASE WHEN tipo='vencimiento' THEN cantidad_dias ELSE 0 END),0) vencidos
        FROM rrhh_vacaciones_movimientos WHERE idempleado=? AND anio=?");
    $st->bind_param('ii',$idempleado,$anio); $st->execute(); $m=$st->get_result()->fetch_assoc(); $st->close();
    // Compatibilidad con solicitudes aprobadas antiguas que aún no tienen movimiento de consumo.
    $st=$db->prepare("SELECT COALESCE(SUM(s.cantidad_dias),0) legacy
        FROM rrhh_solicitudes_ausencia s
        WHERE s.idempleado=? AND s.tipo='vacaciones' AND s.estado='aprobada'
          AND YEAR(s.fecha_desde)=? AND NOT EXISTS (
            SELECT 1 FROM rrhh_vacaciones_movimientos mv
            WHERE mv.idsolicitud=s.idsolicitud AND mv.tipo='consumo')");
    $st->bind_param('ii',$idempleado,$anio); $st->execute(); $legacy=(int)$st->get_result()->fetch_assoc()['legacy']; $st->close();
    $usados=(int)$m['consumidos']+$legacy;
    $disponible=$base+$ajuste+(int)$m['reintegrados']-(int)$m['vencidos']-$usados;
    return ['utilizados'=>$usados,'reintegrados'=>(int)$m['reintegrados'],'vencidos'=>(int)$m['vencidos'],'disponible'=>$disponible];
}
function vacMovimiento(mysqli $db,int $emp,int $anio,?int $sol,string $tipo,int $dias,string $desc,int $usuario): void {
    $st=$db->prepare("INSERT INTO rrhh_vacaciones_movimientos
        (idempleado,anio,idsolicitud,tipo,cantidad_dias,descripcion,idusuario_registro)
        VALUES (?,?,?,?,?,?,?)");
    $st->bind_param('iiisisi',$emp,$anio,$sol,$tipo,$dias,$desc,$usuario);
    $st->execute(); $st->close();
}

try {
    $userAuth=validarTokenAPI($mysqli ?? null);
    validarPermisoEndpoint($mysqli,$userAuth);
    $empresa=obtenerEmpresaActual($mysqli,$userAuth);
    if(empty($empresa['idempresa'])) vacJson('error','No se pudo determinar la empresa activa.');
    $usuario=(int)($userAuth['idusuario']??0);
    $mysqli=conectarDBEmpresa($mysqli,(int)$empresa['idempresa'],'DATOS');
    $action=$_GET['action']??$_POST['action']??'';

    if($action==='listar' && $_SERVER['REQUEST_METHOD']==='GET'){
        $anio=(int)($_GET['anio']??date('Y'));
        if($anio<2000||$anio>2100) vacJson('error','Año inválido.');
        $st=$mysqli->prepare("SELECT idempleado,documento,nombre,apellido,fecha_inicio FROM empleados WHERE activo=1 ORDER BY apellido,nombre");
        $st->execute();$rs=$st->get_result();$rows=[];
        while($e=$rs->fetch_assoc()){
            $id=(int)$e['idempleado']; $ant=vacAntiguedad($mysqli,(string)$e['fecha_inicio'],$anio);
            $regla=vacRegla($mysqli,$ant);
            $st2=$mysqli->prepare("SELECT idsaldo,idregla,antiguedad_calculada,dias_asignados,ajuste_dias,observaciones FROM rrhh_vacaciones_saldos WHERE idempleado=? AND anio=?");
            $st2->bind_param('ii',$id,$anio);$st2->execute();$saldo=$st2->get_result()->fetch_assoc();$st2->close();
            $base=$saldo?(int)$saldo['dias_asignados']:(int)($regla['dias_asignados']??0);
            $ajuste=$saldo?(int)$saldo['ajuste_dias']:0;
            $calc=vacSaldo($mysqli,$id,$anio,$base,$ajuste);
            $e['idsaldo']=$saldo?(int)$saldo['idsaldo']:null;
            $e['idregla']=$saldo?(int)$saldo['idregla']:($regla?(int)$regla['idregla']:null);
            $e['antiguedad_calculada']=$saldo?(int)$saldo['antiguedad_calculada']:$ant;
            $e['dias_asignados']=$base;$e['ajuste_dias']=$ajuste;
            $e['dias_utilizados']=$calc['utilizados'];$e['saldo_disponible']=$calc['disponible'];
            $e['dias_regla']=$regla?(int)$regla['dias_asignados']:null;
            $e['regla_sugerida']=$regla?(int)$regla['idregla']:null;
            $e['observaciones']=$saldo['observaciones']??'';
            $e['tiene_regla']=(bool)$regla;
            $rows[]=$e;
        }
        $st->close();vacJson('ok','Saldos calculados.',['anio'=>$anio,'empleados'=>$rows]);
    }

    if($action==='generar_periodo' && $_SERVER['REQUEST_METHOD']==='POST'){
        $emp=(int)($_POST['idempleado']??0);
        $desde=trim((string)($_POST['fecha_desde']??''));$hasta=trim((string)($_POST['fecha_hasta']??''));
        $motivo=trim((string)($_POST['motivo']??''));
        if($emp<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$desde)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$hasta)) vacJson('error','Empleado y fechas válidas son obligatorios.');
        $anio=(int)substr($desde,0,4);
        if((int)substr($hasta,0,4)!==$anio) vacJson('error','El período debe estar dentro del mismo año de vacaciones.');
        $dias=vacDiasHabiles($desde,$hasta);
        if($dias<=0) vacJson('error','El período no contiene días hábiles.');
        if(mb_strlen($motivo)>500) vacJson('error','El motivo no puede superar 500 caracteres.');

        $mysqli->begin_transaction();
        try{
            $st=$mysqli->prepare("SELECT idempleado,fecha_inicio FROM empleados WHERE idempleado=? AND activo=1 FOR UPDATE");
            $st->bind_param('i',$emp);$st->execute();$empleado=$st->get_result()->fetch_assoc();$st->close();
            if(!$empleado) throw new RuntimeException('Empleado activo no encontrado.');
            $ant=vacAntiguedad($mysqli,(string)$empleado['fecha_inicio'],$anio);$regla=vacRegla($mysqli,$ant);
            if(!$regla) throw new RuntimeException('No existe una regla activa para la antigüedad del empleado.');

            // Crear el saldo base solo al confirmar el primer período del año.
            $st=$mysqli->prepare("SELECT idsaldo,idregla,antiguedad_calculada,dias_asignados,ajuste_dias FROM rrhh_vacaciones_saldos WHERE idempleado=? AND anio=? FOR UPDATE");
            $st->bind_param('ii',$emp,$anio);$st->execute();$saldo=$st->get_result()->fetch_assoc();$st->close();
            if(!$saldo){
                $idRegla=(int)$regla['idregla'];$base=(int)$regla['dias_asignados'];$ajuste=0;
                $st=$mysqli->prepare("INSERT INTO rrhh_vacaciones_saldos (idempleado,anio,idregla,antiguedad_calculada,dias_asignados,ajuste_dias) VALUES (?,?,?,?,?,0)");
                $st->bind_param('iiiii',$emp,$anio,$idRegla,$ant,$base);$st->execute();$idSaldo=(int)$mysqli->insert_id;$st->close();
                vacMovimiento($mysqli,$emp,$anio,null,'asignacion',$base,'Asignación anual según regla de antigüedad '.$idRegla,$usuario);
            }else{$base=(int)$saldo['dias_asignados'];$ajuste=(int)$saldo['ajuste_dias'];$idSaldo=(int)$saldo['idsaldo'];}
            $calc=vacSaldo($mysqli,$emp,$anio,$base,$ajuste);
            if($dias>$calc['disponible']) throw new RuntimeException("El período requiere {$dias} días hábiles y el saldo disponible es {$calc['disponible']}.");

            // Evitar períodos superpuestos con solicitudes activas (pendientes o aprobadas).
            $st=$mysqli->prepare("SELECT idsolicitud FROM rrhh_solicitudes_ausencia
                WHERE idempleado=? AND tipo='vacaciones' AND estado IN ('pendiente','aprobada')
                  AND NOT (fecha_hasta < ? OR fecha_desde > ?) LIMIT 1");
            $st->bind_param('iss',$emp,$desde,$hasta);$st->execute();$over=$st->get_result()->fetch_assoc();$st->close();
            if($over) throw new RuntimeException('El período se superpone con otra solicitud de vacaciones pendiente o aprobada.');

            $estado='aprobada';$st=$mysqli->prepare("INSERT INTO rrhh_solicitudes_ausencia
                (idempleado,tipo,fecha_desde,fecha_hasta,cantidad_dias,motivo,estado,idusuario_solicita,idusuario_resuelve,fecha_resolucion)
                VALUES (?,'vacaciones',?,?,?,?,?,?,?,NOW())");
            $st->bind_param('ississii',$emp,$desde,$hasta,$dias,$motivo,$estado,$usuario,$usuario);
            $st->execute();$idSolicitud=(int)$mysqli->insert_id;$st->close();
            vacMovimiento($mysqli,$emp,$anio,$idSolicitud,'consumo',$dias,'Consumo por período de vacaciones '.$desde.' al '.$hasta,$usuario);
            @registrarLog($mysqli,'generar_periodo_vacaciones','rrhh_solicitudes_ausencia',$idSolicitud,$usuario,null,
                ['idempleado'=>$emp,'anio'=>$anio,'fecha_desde'=>$desde,'fecha_hasta'=>$hasta,'cantidad_dias'=>$dias,'estado'=>$estado]);
            $mysqli->commit();
        }catch(Throwable $e){$mysqli->rollback();throw $e;}
        vacJson('ok','Período de vacaciones registrado.',['idsolicitud'=>$idSolicitud,'cantidad_dias'=>$dias]);
    }
    vacJson('error','Acción o método no válido.');
}catch(Throwable $e){
    error_log('API RRHH vacaciones: '.$e->getMessage());
    vacJson('error',$e->getMessage());
}