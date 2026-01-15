"""
Point d'entrée principal de l'application FastAPI.
SaaS Multi-Boutiques - Personnalisation Textile
"""
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from sqlalchemy import text

from app.api.v1.router import router as api_v1_router
from app.config import get_settings
from app.database import async_session_maker

settings = get_settings()


# ----------------------------------------------
# Lifespan - Startup / Shutdown
# ----------------------------------------------
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Gestion du cycle de vie de l'application."""
    # Startup
    print(f"[{settings.app_name}] Starting in {settings.app_env} mode...")
    yield
    # Shutdown
    print(f"[{settings.app_name}] Shutting down...")


# ----------------------------------------------
# Application FastAPI
# ----------------------------------------------
app = FastAPI(
    title=settings.app_name,
    description="API Backend SaaS Multi-Boutiques - Personnalisation Textile",
    version="0.1.0",
    docs_url="/docs" if settings.debug else None,
    redoc_url="/redoc" if settings.debug else None,
    openapi_url="/openapi.json" if settings.debug else None,
    lifespan=lifespan,
)


# ----------------------------------------------
# CORS Middleware
# ----------------------------------------------
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ----------------------------------------------
# Routes de base (Health Check)
# ----------------------------------------------
@app.get("/health", tags=["Health"])
async def health_check():
    """
    Vérifie l'état de l'application et de la connexion DB.
    """
    db_status = "disconnected"

    try:
        async with async_session_maker() as session:
            await session.execute(text("SELECT 1"))
            db_status = "connected"
    except Exception as e:
        db_status = f"error: {str(e)}"

    return {
        "status": "healthy" if db_status == "connected" else "degraded",
        "database": db_status,
        "environment": settings.app_env,
        "debug": settings.debug,
    }


@app.get("/", tags=["Root"])
async def root():
    """Route racine - Info API."""
    return {
        "name": settings.app_name,
        "version": "0.1.0",
        "docs": "/docs" if settings.debug else "disabled",
    }


# ----------------------------------------------
# Include API Routers
# ----------------------------------------------
app.include_router(api_v1_router)


# ----------------------------------------------
# Exception Handlers globaux
# ----------------------------------------------
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    """Handler global pour les exceptions non gérées."""
    if settings.debug:
        return JSONResponse(
            status_code=500,
            content={
                "error": "internal_server_error",
                "message": str(exc),
                "type": type(exc).__name__,
            },
        )
    return JSONResponse(
        status_code=500,
        content={
            "error": "internal_server_error",
            "message": "Une erreur interne est survenue.",
        },
    )
