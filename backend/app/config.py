"""
Configuration centralisée via Pydantic Settings.
Charge les variables depuis .env
"""
from functools import lru_cache
from typing import List

from pydantic import field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Configuration de l'application."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    # ----------------------------------------------
    # Application
    # ----------------------------------------------
    app_name: str = "SaaS-Personnalisation"
    app_env: str = "development"
    debug: bool = True

    # ----------------------------------------------
    # Database - MySQL
    # ----------------------------------------------
    mysql_host: str
    mysql_port: int = 3306
    mysql_user: str
    mysql_password: str
    mysql_database: str

    @property
    def database_url(self) -> str:
        """Construit l'URL de connexion MySQL async."""
        return (
            f"mysql+aiomysql://{self.mysql_user}:{self.mysql_password}"
            f"@{self.mysql_host}:{self.mysql_port}/{self.mysql_database}"
        )

    @property
    def database_url_sync(self) -> str:
        """URL sync pour Alembic."""
        return (
            f"mysql+pymysql://{self.mysql_user}:{self.mysql_password}"
            f"@{self.mysql_host}:{self.mysql_port}/{self.mysql_database}"
        )

    # ----------------------------------------------
    # Security - JWT
    # ----------------------------------------------
    jwt_secret_key: str
    jwt_algorithm: str = "HS256"
    jwt_access_token_expire_minutes: int = 30
    jwt_refresh_token_expire_days: int = 7

    # ----------------------------------------------
    # SuperAdmin
    # ----------------------------------------------
    superadmin_email: str = ""
    superadmin_password: str = ""

    # ----------------------------------------------
    # OAuth - Google
    # ----------------------------------------------
    google_client_id: str = ""
    google_client_secret: str = ""
    google_redirect_uri: str = ""

    # ----------------------------------------------
    # Email - Brevo
    # ----------------------------------------------
    brevo_api_key: str = ""
    brevo_sender_email: str = ""
    brevo_sender_name: str = ""

    # ----------------------------------------------
    # CORS
    # ----------------------------------------------
    cors_origins: str = ""

    @property
    def cors_origins_list(self) -> List[str]:
        """Convertit la string CORS en liste."""
        if not self.cors_origins:
            return ["*"] if self.debug else []
        return [origin.strip() for origin in self.cors_origins.split(",")]

    # ----------------------------------------------
    # Server
    # ----------------------------------------------
    server_host: str = "0.0.0.0"
    server_port: int = 8000

    # ----------------------------------------------
    # Helpers
    # ----------------------------------------------
    @property
    def is_production(self) -> bool:
        return self.app_env == "production"

    @property
    def is_development(self) -> bool:
        return self.app_env == "development"


@lru_cache()
def get_settings() -> Settings:
    """
    Retourne l'instance de configuration (singleton).
    Utilise lru_cache pour ne charger qu'une fois.
    """
    return Settings()
