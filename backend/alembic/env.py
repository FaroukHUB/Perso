"""
Alembic environment configuration.
"""
from logging.config import fileConfig

from alembic import context
from sqlalchemy import engine_from_config, pool

# Import config et models
from app.config import get_settings
from app.models.base import Base

# Importer tous les modèles ici pour que Alembic les détecte
# from app.models.shop import Shop
# from app.models.user import User
# etc.

# this is the Alembic Config object
config = context.config

# Interpret the config file for Python logging.
if config.config_file_name is not None:
    fileConfig(config.config_file_name)

# MetaData for 'autogenerate' support
target_metadata = Base.metadata

# Get settings
settings = get_settings()


def get_url():
    """Récupère l'URL de la DB depuis les settings."""
    return settings.database_url_sync


def run_migrations_offline() -> None:
    """
    Run migrations in 'offline' mode.
    Generates SQL script without connecting to DB.
    """
    url = get_url()
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
        compare_type=True,
        compare_server_default=True,
    )

    with context.begin_transaction():
        context.run_migrations()


def run_migrations_online() -> None:
    """
    Run migrations in 'online' mode.
    Creates an Engine and connects to DB.
    """
    configuration = config.get_section(config.config_ini_section)
    configuration["sqlalchemy.url"] = get_url()

    connectable = engine_from_config(
        configuration,
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
    )

    with connectable.connect() as connection:
        context.configure(
            connection=connection,
            target_metadata=target_metadata,
            compare_type=True,
            compare_server_default=True,
        )

        with context.begin_transaction():
            context.run_migrations()


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
