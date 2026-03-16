---
trigger: always_on
---

# Reglas de Arquitectura del Proyecto (DDD y SOLID)

Para mantener la coherencia y escalabilidad del proyecto, todo desarrollo DEBE regirse por las siguientes directrices basadas en Domain-Driven Design (DDD) y principios SOLID. **Toda la lógica debe vivir estructurada dentro del directorio del módulo correspondiente.**

## 1. Creación de Módulos (Obligatorio)
Para crear un nuevo módulo se **DEBE usar exclusivamente el comando automatizado** de Artisan. Esto garantiza que se cree toda la estructura (Actions, DTOs, Controllers, Requests, Models) de forma estandarizada.
```bash
php artisan module:create NombreDelModulo
```

## 2. Flujo CRUD Funcional (Reglas DDD/SOLID)
El desarrollo de cualquier CRUD o lógica de negocio debe respetar esta estricta separación de responsabilidades dentro de su propio módulo:

### A. Controladores (Controllers)
- **El Controlador solo DELEGA.**
- No debe contener lógica de negocio, validaciones nativas, ni consultas directas a la base de datos (Eloquent).
- Su única función es recibir el flujo, pasarlo al Action, y retornar la respuesta utilizando el Response homologado.
- Debe usar el trait `App\Traits\ApiResponse` para entregar siempre `$this->success()` o `$this->error()`.

### B. Validación (Requests)
- Toda validación de entrada de datos se delega exclusivamente a una clase `FormRequest` ubicada en `modules/{Modulo}/Http/Requests/`.

### C. Transferencia de Datos (DTOs)
- Los datos validados por el Request se encapsulan en un **Data Transfer Object (DTO)** (`modules/{Modulo}/DataTransferObjects/`).
- Estos objetos fuertemente tipados son los únicos que viajan desde el Controlador hacia el Action.

### D. Lógica de Negocio (Actions)
- **Los Actions EJECUTAN la lógica.**
- Todo el código que interactúa con el modelo, guarda, actualiza, elimina o procesa datos, vive en una clase Action con **Responsabilidad Única**.
- Están ubicados en `modules/{Modulo}/Actions/` (ej. `RouteStoreAction`, `RouteListAction`).
- Reciben el DTO, operan la Base de Datos, y retornan el modelo puro.

### E. Formateo de Respuesta (Resources)
- La respuesta que se enviará al cliente HTTP jamás debe ser el Modelo de base de datos directo, sino que debe pasar por un `JsonResource` ubicado en `modules/{Modulo}/Http/Resources/`.
- Esto envuelve y piratea los datos al cliente protegiendo la estructura interna.

---
**Resumen del Flujo Intocable:**
`Ruta` -> `Controller` -> `Request (Validación)` -> `DTO (Empaquetado)` -> `Action (Lógica de Negocio)` -> `Resource (Formateo)` -> `Controller (Retorna ApiResponse)`

Este proyecto de negocios consta de 3 carpetas

PolarApi que es el Back End del sistema sera un sistema Multitenant que tendra la capacidad de sacar estadisticas de rutas(Cada ruta es una bd)que tiene sus propios clientres productos ventas y detalle de ventas asignados como otras tablas en general

Tambien por el momento puede entregar reportes .csv

PolarReact es el front se conecta a Ploar api para mostrar datos de estadisticas Esta realizado en react y usa vuexy

ProductosPolarApi

Este back end de laravel sirve para descargar toda la base de datos de productos que nos entregara Polar ademas de sus clientes.. por el momento es un proyecto que solo se encargara de guardar assivamente data 

COnsta de un swagger y de una colecction postam que siempre debe ser actualizada al hacer cambios se rige de la misma manera modular que lo hace  PolarApi 

