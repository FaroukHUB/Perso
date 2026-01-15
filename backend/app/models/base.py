"""
Classe de base SQLAlchemy et mixins réutilisables.
"""
import uuid
from datetime import datetime
from typing import Any

from sqlalchemy import DateTime, String, func
from sqlalchemy.dialects.mysql import CHAR
from sqlalchemy.orm import DeclarativeBase, Mapped, declared_attr, mapped_column


class Base(DeclarativeBase):
    """
    Classe de base pour tous les modèles SQLAlchemy.
    """

    # Génère automatiquement le nom de table depuis le nom de classe
    @declared_attr.directive
    def __tablename__(cls) -> str:
        # CamelCase -> snake_case + pluriel simple
        # Ex: PlatformUser -> platform_users
        name = cls.__name__
        result = [name[0].lower()]
        for char in name[1:]:
            if char.isupper():
                result.append("_")
                result.append(char.lower())
            else:
                result.append(char)
        return "".join(result) + "s"


class UUIDMixin:
    """
    Mixin pour ID en UUID.
    Utilisé pour toutes les tables.
    """

    id: Mapped[str] = mapped_column(
        CHAR(36),
        primary_key=True,
        default=lambda: str(uuid.uuid4()),
    )


class TimestampMixin:
    """
    Mixin pour les timestamps automatiques.
    """

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False,
    )


class SoftDeleteMixin:
    """
    Mixin pour la suppression logique.
    """

    deleted_at: Mapped[datetime | None] = mapped_column(
        DateTime(timezone=True),
        nullable=True,
        default=None,
    )

    @property
    def is_deleted(self) -> bool:
        return self.deleted_at is not None


class ShopBoundMixin:
    """
    Mixin pour les tables métier liées à une boutique.
    OBLIGATOIRE pour toutes les tables métier.
    """

    # shop_id sera défini comme FK dans chaque modèle
    # Ce mixin sert de marqueur et de documentation
    pass
