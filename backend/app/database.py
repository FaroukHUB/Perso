"""
Configuration de la base de données SQLAlchemy 2.0 (async).
"""
from typing import AsyncGenerator

from sqlalchemy.ext.asyncio import (
    AsyncSession,
    async_sessionmaker,
    create_async_engine,
)
from sqlalchemy.pool import NullPool

from app.config import get_settings

settings = get_settings()

# ----------------------------------------------
# Engine async
# ----------------------------------------------
engine = create_async_engine(
    settings.database_url,
    echo=settings.debug,  # Log SQL en mode debug
    pool_pre_ping=True,   # Vérifie la connexion avant utilisation
    poolclass=NullPool if settings.is_development else None,
)

# ----------------------------------------------
# Session factory
# ----------------------------------------------
async_session_maker = async_sessionmaker(
    engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autocommit=False,
    autoflush=False,
)


# ----------------------------------------------
# Dependency pour FastAPI
# ----------------------------------------------
async def get_db() -> AsyncGenerator[AsyncSession, None]:
    """
    Dependency qui fournit une session DB.
    Utilisé dans les routes via Depends(get_db).
    """
    async with async_session_maker() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise
        finally:
            await session.close()


# ----------------------------------------------
# Helpers
# ----------------------------------------------
async def check_database_connection() -> bool:
    """Vérifie que la connexion à la DB fonctionne."""
    try:
        async with async_session_maker() as session:
            await session.execute("SELECT 1")
            return True
    except Exception:
        return False
