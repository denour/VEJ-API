pu# Análisis del Flujo de Generación de Imágenes

## Resumen del Problema
La imagen se genera correctamente en NanoBanana, pero no se guarda en el campo `cover_image` del Post en producción.

---

## Flujo Completo del Sistema

### Paso 1: Inicio de Generación (Job o Servicio)

**Archivo:** `app/Jobs/GenerateModelImage.php`

1. Se ejecuta el Job `GenerateModelImage` con un modelo (Post, Author, etc.)
2. El Job llama a `ImageGeneratorInterface::generate()` con el prompt
3. NanoBanana API responde con un `taskId`
4. Se crea un registro en `image_generation_requests`:
   ```
   external_id: taskId de NanoBanana
   targetable_type: "App\Models\Post"
   targetable_id: ID del Post (ULID)
   status: "pending"
   metadata: { attribute: "cover_image", model_name: "Post" }
   ```

### Paso 2: NanoBanana Procesa la Imagen

- NanoBanana genera la imagen de forma asíncrona
- Cuando termina, envía un webhook a `api/webhooks/banana`

### Paso 3: Webhook Recibe el Callback

**Archivo:** `app/Http/Controllers/Api/BananaCallbackController.php`

1. **Extracción del payload** (líneas 21-27):
   ```php
   $taskId = data_get($payload, 'taskId') ?? data_get($payload, 'data.taskId') ?? ...
   $imageUrl = data_get($payload, 'imageUrl') ?? data_get($payload, 'resultImageUrl') ?? ...
   ```

2. **Búsqueda del request** (líneas 35-37):
   ```php
   $requestRecord = ImageGenerationRequest::query()
       ->where('external_id', (string) $taskId)
       ->first();
   ```

3. **Carga del modelo target** (líneas 79-98):
   - Intenta cargar por `targetable_type` + `targetable_id`
   - Fallback a relación `morphTo`
   - Fallback legacy a `post_id`

4. **Descarga y guardado de imagen** (líneas 62-131):
   - Descarga la imagen desde la URL de NanoBanana
   - Guarda en S3 con ruta: `posts/{slug}-{id}.png`

5. **Actualización del Post** (líneas 136-164):
   ```php
   $target->{$attribute} = $publicUrl;  // cover_image = URL
   $saved = $target->save();
   ```

---

## Puntos Críticos de Fallo

### 1. El `ImageGenerationRequest` NO se encuentra

**Ubicación:** Líneas 35-44

**Síntomas:**
- Response HTTP 202 con mensaje "No matching request found for taskId"
- El `ImageGenerationRequest` tiene status "pending" permanentemente

**Posibles causas:**
- El `taskId` del webhook no coincide con el `external_id` guardado
- El request no se creó (el Job falló silenciosamente)
- Problema de tipo de dato: el webhook envía número pero la BD tiene string

### 2. El `imageUrl` está vacío en el payload

**Ubicación:** Líneas 54-59

**Síntomas:**
- Response HTTP 202 con mensaje "Accepted - awaiting image URL"
- Status cambia a "processing" pero nunca a "completed"

**Posibles causas:**
- NanoBanana envía múltiples webhooks (uno sin URL, otro con URL)
- El payload tiene estructura diferente a la esperada
- NanoBanana cambió el formato de respuesta

### 3. El modelo `target` NO se carga

**Ubicación:** Líneas 79-98

**Síntomas:**
- Log warning: "Cannot update model attribute - missing target or attribute"
- La imagen se guarda en S3 pero el Post no se actualiza

**Posibles causas:**
- `targetable_type` es incorrecto (ej: "Post" en vez de "App\Models\Post")
- `targetable_id` no existe en la base de datos
- El Post fue eliminado entre la creación del request y el webhook

### 4. El `$target->save()` falla silenciosamente

**Ubicación:** Líneas 142-163

**Síntomas:**
- Log error: "Attribute update failed to persist"
- El request queda como "completed" pero el Post no tiene imagen

**Posibles causas:**
- Validación del modelo falla (campo requerido faltante)
- Observer o Event bloquea el guardado
- Problema de permisos en la base de datos
- El atributo `cover_image` no está en `$fillable` (ESTÁ, línea 24 de Post.php)

### 5. Error en la descarga de imagen

**Ubicación:** Líneas 62-72

**Síntomas:**
- Status "failed" con error_message "Failed to download image from provider"

**Posibles causas:**
- La URL de NanoBanana expiró
- Timeout de 120 segundos excedido
- La imagen es muy grande
- El servidor no puede conectar a NanoBanana (firewall, DNS)

### 6. Error al guardar en S3

**Ubicación:** Línea 131

**Síntomas:**
- Excepción capturada, status "failed"
- Log error con el mensaje de excepción

**Posibles causas:**
- Credenciales de S3 incorrectas o expiradas
- Bucket no existe o no tiene permisos
- Límite de almacenamiento excedido

---

## Diagnóstico Recomendado

### 1. Revisar Logs de Laravel
```bash
tail -f storage/logs/laravel.log | grep -E "(Target loaded|Simple attribute|Cannot update|Attribute update failed)"
```

Buscar estos mensajes clave:
- `"Target loaded for image generation"` → ¿Se cargó el Post?
- `"Simple attribute update"` → ¿`saved` es `true` o `false`?
- `"Cannot update model attribute"` → No se encontró el target
- `"Attribute update failed to persist"` → El save() no funcionó

### 2. Verificar el Payload del Webhook
```bash
# Revisar el metadata del ImageGenerationRequest
SELECT id, external_id, status, metadata FROM image_generation_requests ORDER BY id DESC LIMIT 10;
```

El campo `metadata->webhook` contiene el payload recibido.

### 3. Verificar el ImageGenerationRequest
```sql
SELECT
    id,
    external_id,
    targetable_type,
    targetable_id,
    status,
    metadata,
    error_message
FROM image_generation_requests
WHERE status != 'completed'
ORDER BY created_at DESC;
```

### 4. Verificar que el Post existe
```sql
SELECT id, title, cover_image
FROM posts
WHERE id = '{targetable_id del request}';
```

---

## Hipótesis Más Probables

### Hipótesis A: El Target no se encuentra
El `targetable_id` guardado no coincide con ningún Post existente, o el tipo está mal formateado.

**Verificación:**
```php
// En tinker
$request = ImageGenerationRequest::latest()->first();
echo $request->targetable_type;  // Debe ser "App\Models\Post"
echo $request->targetable_id;    // Debe ser un ULID válido
$post = Post::find($request->targetable_id);
dd($post);  // ¿Es null?
```

### Hipótesis B: El webhook llega con formato diferente
NanoBanana puede enviar el payload con una estructura distinta a la esperada.

**Verificación:**
```php
$request = ImageGenerationRequest::latest()->first();
dd($request->metadata['webhook']);  // Ver estructura real
```

### Hipótesis C: El Post tiene un mutator/accessor que interfiere
Si `cover_image` tuviera un accessor que transforma el valor, podría causar problemas.

**Estado actual:** El accessor está comentado en `Post.php` (líneas 54-60), por lo que NO debería ser el problema.

### Hipótesis D: El Job no se está ejecutando
El Job está en cola pero nunca se procesa.

**Verificación:**
```bash
php artisan queue:work --once
# Ver si hay jobs pendientes
```

### Hipótesis E: Múltiples webhooks con el mismo taskId
Si NanoBanana envía múltiples callbacks, el primero puede crear un request duplicado que "roba" la actualización.

**Verificación:**
```sql
SELECT external_id, COUNT(*) as count
FROM image_generation_requests
GROUP BY external_id
HAVING count > 1;
```

---

## Información de Contexto

### Modelos Involucrados
- `Post` usa ULIDs (`HasUlids` trait)
- `ImageGenerationRequest` usa IDs numéricos auto-incrementales
- Relación polimórfica: `targetable_type` + `targetable_id`

### Configuración Relevante
- Storage: S3 con visibilidad pública
- Webhook URL: `api/webhooks/banana`
- Timeout HTTP: 120 segundos

### Atributos del Post
`cover_image` está en `$fillable` y no tiene cast especial.
