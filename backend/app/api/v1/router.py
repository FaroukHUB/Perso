"""
Router principal API v1.
Agrège tous les sous-routers.
"""
from fastapi import APIRouter

router = APIRouter(prefix="/api/v1")

# Les sous-routers seront ajoutés ici au Step 1
# Exemple:
# from app.api.v1 import auth, shops
# router.include_router(auth.router, prefix="/auth", tags=["Auth"])
# router.include_router(shops.router, prefix="/platform/shops", tags=["Shops"])
