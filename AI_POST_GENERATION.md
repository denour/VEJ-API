# Sistema de Generación Automática de Posts con IA

Este sistema genera automáticamente posts para el blog de jardinería usando inteligencia artificial.

## 🌟 Características

- **Generación automática diaria**: Un post nuevo cada día a las 9:00 AM
- **Proveedores intercambiables**: Cambia fácilmente entre diferentes modelos de IA
- **Generación inteligente de bloques**: Cada bloque de contenido se genera con un prompt específico
- **Generación de imágenes**: Crea imágenes personalizadas para cada post
- **Estructura flexible**: Soporta párrafos, headings, imágenes, listas y citas

## 📋 Configuración

### 1. Variables de Entorno

Copia las siguientes variables a tu archivo `.env`:

```env
# AI Configuration
AI_TEXT_PROVIDER=openai
AI_IMAGE_PROVIDER=banana

# OpenAI API Configuration
OPENAI_API_KEY=tu-api-key-aqui
OPENAI_MODEL=gpt-4

# Banana API Configuration
BANANA_API_KEY=tu-api-key-aqui
BANANA_MODEL=nano-banana
```

### 2. Proveedores Disponibles

#### Texto
- **OpenAI**: GPT-4, GPT-3.5-turbo, etc.
- Fácil de agregar más (Anthropic Claude, etc.)

#### Imágenes
- **Banana**: Nano Banana, Stable Diffusion
- **OpenAI**: DALL-E 3
- Fácil de agregar más (Stability AI, Midjourney, etc.)

### 3. Cambiar Proveedores

Para cambiar de proveedor, simplemente actualiza las variables de entorno:

```env
# Usar GPT-3.5 en lugar de GPT-4
OPENAI_MODEL=gpt-3.5-turbo

# Usar DALL-E en lugar de Banana
AI_IMAGE_PROVIDER=openai
```

## 🚀 Uso

### Generar un Post Manualmente

```bash
vendor/bin/sail artisan posts:generate-daily
```

### Generación Automática

El sistema está configurado para ejecutarse automáticamente cada día a las 9:00 AM (zona horaria America/Mexico_City).

Para que esto funcione, necesitas configurar el cron de Laravel:

```bash
# En producción, agrega esto a tu crontab:
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Con Laravel Sail/Docker, puedes usar:

```bash
vendor/bin/sail artisan schedule:work
```

### Ver la Programación

```bash
vendor/bin/sail artisan schedule:list
```

## 🏗️ Arquitectura

### Estructura de Archivos

```
app/
├── Contracts/AI/
│   ├── TextGeneratorInterface.php      # Interface para generadores de texto
│   └── ImageGeneratorInterface.php     # Interface para generadores de imágenes
├── Services/AI/
│   ├── OpenAITextGenerator.php         # Implementación de OpenAI
│   ├── BananaImageGenerator.php        # Implementación de Banana
│   └── PostGeneratorService.php        # Servicio principal
├── Providers/
│   └── AIServiceProvider.php           # Provider para DI
└── Console/Commands/
    └── GenerateDailyPost.php           # Comando Artisan
```

### Proceso de Generación

1. **Generar Estructura**: El servicio crea la idea del post y define los bloques
2. **Generar Contenido por Bloque**: Cada bloque se genera individualmente con un prompt específico
3. **Generar Imágenes**: Para bloques de imagen, primero se genera un prompt detallado, luego la imagen
4. **Crear Tabla de Contenido**: Se genera automáticamente desde los headings
5. **Guardar Post**: El post se guarda como borrador en la base de datos

## 🔧 Agregar Nuevos Proveedores

### Proveedor de Texto

1. Crear una nueva clase que implemente `TextGeneratorInterface`:

```php
<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;

class AnthropicTextGenerator implements TextGeneratorInterface
{
    public function generate(string $prompt, array $options = []): string
    {
        // Implementación
    }

    public function getProviderName(): string
    {
        return 'Anthropic';
    }
}
```

2. Registrar en `AIServiceProvider`:

```php
return match ($provider) {
    'openai' => new OpenAITextGenerator(...),
    'anthropic' => new AnthropicTextGenerator(...),
    default => throw new \InvalidArgumentException("Unknown text provider: {$provider}"),
};
```

3. Agregar configuración en `config/ai.php`

4. Actualizar `.env.example`

### Proveedor de Imágenes

Mismo proceso pero implementando `ImageGeneratorInterface`.

## 📝 Personalización

### Modificar Prompts

Los prompts están en `PostGeneratorService.php`. Puedes ajustarlos según tus necesidades:

- `generatePostStructure()`: Prompt para la estructura general
- `generateParagraph()`: Prompt para párrafos
- `generateHeading()`: Prompt para títulos
- `generateImage()`: Prompt para descripciones de imágenes
- `generateList()`: Prompt para listas
- `generateQuote()`: Prompt para citas

### Cambiar Horario

Edita `bootstrap/app.php`:

```php
$schedule->command('posts:generate-daily')
    ->dailyAt('14:00')  // Cambiar a 2 PM
    ->timezone('America/Mexico_City');
```

O usar otra frecuencia:

```php
// Cada hora
->hourly()

// Dos veces al día
->twiceDaily(9, 18)

// Cada lunes a las 9 AM
->weeklyOn(1, '9:00')
```

## 🧪 Testing

El sistema genera posts en estado `draft` para que puedas revisarlos antes de publicar.

Revisa los posts generados en el panel de Filament en `/ecommerce/posts`.

## ⚠️ Consideraciones

- **Costos de API**: Cada generación hace múltiples llamadas a las APIs de IA
- **Tiempo de Ejecución**: Puede tomar varios minutos generar un post completo
- **Revisión Manual**: Los posts se crean como borradores para revisión
- **Rate Limits**: Ten cuidado con los límites de las APIs

## 🔍 Troubleshooting

### Error: "Target [TextGeneratorInterface] is not instantiable"

Asegúrate de que `AIServiceProvider` está registrado en `bootstrap/providers.php`.

### Error de API

Verifica que tus API keys sean correctas y tengas saldo/créditos disponibles.

### El comando no se ejecuta automáticamente

Asegúrate de que el cron de Laravel está configurado o ejecuta `schedule:work` en desarrollo.

## 📚 Recursos

- [OpenAI API Documentation](https://platform.openai.com/docs)
- [Laravel Task Scheduling](https://laravel.com/docs/12.x/scheduling)
- [Laravel Service Container](https://laravel.com/docs/12.x/container)
