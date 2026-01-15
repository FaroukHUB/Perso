"""
Exceptions métier personnalisées.
"""
from fastapi import HTTPException, status


# ----------------------------------------------
# Exceptions Tenant / Shop
# ----------------------------------------------
class ShopNotFoundError(HTTPException):
    """Domaine non reconnu - aucune boutique associée."""

    def __init__(self, domain: str):
        super().__init__(
            status_code=status.HTTP_404_NOT_FOUND,
            detail={
                "error": "shop_not_found",
                "message": f"Aucune boutique trouvée pour le domaine: {domain}",
            },
        )


class ShopSuspendedError(HTTPException):
    """Boutique suspendue / inactive."""

    def __init__(self, shop_name: str = ""):
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={
                "error": "shop_suspended",
                "message": "Cette boutique est temporairement suspendue.",
            },
        )


class ShopRequiredError(HTTPException):
    """Tentative d'accès à une route boutique sans shop_id résolu."""

    def __init__(self):
        super().__init__(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "error": "shop_required",
                "message": "Cette route nécessite un contexte boutique valide.",
            },
        )


# ----------------------------------------------
# Exceptions Auth
# ----------------------------------------------
class InvalidCredentialsError(HTTPException):
    """Email ou mot de passe incorrect."""

    def __init__(self):
        super().__init__(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={
                "error": "invalid_credentials",
                "message": "Email ou mot de passe incorrect.",
            },
            headers={"WWW-Authenticate": "Bearer"},
        )


class TokenExpiredError(HTTPException):
    """Token JWT expiré."""

    def __init__(self):
        super().__init__(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={
                "error": "token_expired",
                "message": "Votre session a expiré. Veuillez vous reconnecter.",
            },
            headers={"WWW-Authenticate": "Bearer"},
        )


class InvalidTokenError(HTTPException):
    """Token JWT invalide."""

    def __init__(self):
        super().__init__(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={
                "error": "invalid_token",
                "message": "Token invalide.",
            },
            headers={"WWW-Authenticate": "Bearer"},
        )


class InsufficientPermissionsError(HTTPException):
    """Permissions insuffisantes pour cette action."""

    def __init__(self, required_role: str = ""):
        message = "Vous n'avez pas les permissions nécessaires."
        if required_role:
            message = f"Cette action nécessite le rôle: {required_role}"
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={
                "error": "insufficient_permissions",
                "message": message,
            },
        )


class UserInactiveError(HTTPException):
    """Compte utilisateur désactivé."""

    def __init__(self):
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={
                "error": "user_inactive",
                "message": "Votre compte a été désactivé.",
            },
        )


# ----------------------------------------------
# Exceptions Ressources
# ----------------------------------------------
class ResourceNotFoundError(HTTPException):
    """Ressource non trouvée."""

    def __init__(self, resource: str, identifier: str = ""):
        message = f"{resource} non trouvé(e)."
        if identifier:
            message = f"{resource} avec l'identifiant '{identifier}' non trouvé(e)."
        super().__init__(
            status_code=status.HTTP_404_NOT_FOUND,
            detail={
                "error": "resource_not_found",
                "resource": resource,
                "message": message,
            },
        )


class ResourceAlreadyExistsError(HTTPException):
    """Ressource déjà existante (conflit)."""

    def __init__(self, resource: str, field: str = ""):
        message = f"Ce(tte) {resource} existe déjà."
        if field:
            message = f"Un(e) {resource} avec ce {field} existe déjà."
        super().__init__(
            status_code=status.HTTP_409_CONFLICT,
            detail={
                "error": "resource_already_exists",
                "resource": resource,
                "message": message,
            },
        )
