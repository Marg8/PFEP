#!/usr/bin/env bash
# =============================================================
# PFEP – GCP Infrastructure Setup
# Cloud VPN + VPC Connector para acceder a red interna (mtapp05)
#
# Uso: source infra/variables.env && bash infra/setup-gcp.sh
# =============================================================
set -euo pipefail

echo "▶ Proyecto: $PROJECT_ID  |  Región: $REGION"
gcloud config set project "$PROJECT_ID"

# ── 1. VPC Network ────────────────────────────────────────────
echo ""
echo "1/7 Creando VPC network..."
gcloud compute networks create "$VPC_NAME" \
  --subnet-mode=custom \
  --quiet || echo "  (ya existe, continuando)"

# Subnet /28 exclusiva para el VPC Connector (requerimiento de Google)
gcloud compute networks subnets create "${VPC_NAME}-subnet" \
  --network="$VPC_NAME" \
  --region="$REGION" \
  --range="$SUBNET_CIDR" \
  --quiet || echo "  (subnet ya existe, continuando)"

# ── 2. Cloud Router ───────────────────────────────────────────
echo ""
echo "2/7 Creando Cloud Router..."
gcloud compute routers create "$ROUTER_NAME" \
  --network="$VPC_NAME" \
  --region="$REGION" \
  --quiet || echo "  (ya existe, continuando)"

# ── 3. Cloud VPN Gateway ──────────────────────────────────────
echo ""
echo "3/7 Creando VPN Gateway..."
gcloud compute vpn-gateways create "$VPN_GW_NAME" \
  --network="$VPC_NAME" \
  --region="$REGION" \
  --quiet || echo "  (ya existe, continuando)"

GCP_VPN_IP=$(gcloud compute vpn-gateways describe "$VPN_GW_NAME" \
  --region="$REGION" \
  --format="value(vpnInterfaces[0].ipAddress)")
echo ""
echo "  ✅ IP pública del VPN Gateway de Google: $GCP_VPN_IP"
echo "  ⚠️  Configura esta IP en el router/firewall de tu red interna."
echo ""

# ── 4. VPN Tunnel ─────────────────────────────────────────────
echo "4/7 Creando VPN Tunnel hacia $CORP_VPN_IP..."
gcloud compute vpn-tunnels create "$TUNNEL_NAME" \
  --vpn-gateway="$VPN_GW_NAME" \
  --vpn-gateway-interface=0 \
  --peer-address="$CORP_VPN_IP" \
  --shared-secret="$VPN_SHARED_SECRET" \
  --ike-version=2 \
  --region="$REGION" \
  --router="$ROUTER_NAME" \
  --quiet || echo "  (ya existe, continuando)"

# ── 5. BGP Interface + Route hacia red interna ────────────────
echo ""
echo "5/7 Agregando ruta estática hacia red interna ($CORP_CIDR)..."
gcloud compute routers add-interface "$ROUTER_NAME" \
  --interface-name="pfep-if-0" \
  --vpn-tunnel="$TUNNEL_NAME" \
  --region="$REGION" \
  --quiet 2>/dev/null || echo "  (interfaz ya existe, continuando)"

gcloud compute routes create "pfep-route-internal" \
  --network="$VPC_NAME" \
  --destination-range="$CORP_CIDR" \
  --next-hop-vpn-tunnel="$TUNNEL_NAME" \
  --next-hop-vpn-tunnel-region="$REGION" \
  --priority=100 \
  --quiet 2>/dev/null || echo "  (ruta ya existe, continuando)"

# ── 6. Serverless VPC Access Connector ───────────────────────
echo ""
echo "6/7 Creando VPC Access Connector..."
gcloud services enable vpcaccess.googleapis.com --quiet

gcloud compute networks vpc-access connectors create "$CONNECTOR_NAME" \
  --network="$VPC_NAME" \
  --region="$REGION" \
  --range="$SUBNET_CIDR" \
  --quiet || echo "  (ya existe, continuando)"

# ── 7. Actualizar Cloud Run con el conector ───────────────────
echo ""
echo "7/7 Conectando Cloud Run a la VPC..."
gcloud run services update "$SERVICE_NAME" \
  --region="$REGION" \
  --vpc-connector="$CONNECTOR_NAME" \
  --vpc-egress=private-ranges-only \
  --set-env-vars="DB_HOST=$CORP_DB_IP,DB_PORT=3306,DB_NAME=materials,DB_USER=mrodriguez,DB_PASS=" \
  --quiet

echo ""
echo "═══════════════════════════════════════════════════════"
echo "✅ Infraestructura lista."
echo ""
echo "SIGUIENTE PASO — Configurar tu router/firewall interno:"
echo ""
echo "  Protocolo  : IKEv2"
echo "  IP remota  : $GCP_VPN_IP   (IP del VPN Gateway de Google)"
echo "  IP local   : $CORP_VPN_IP  (tu IP pública)"
echo "  Red remota : $SUBNET_CIDR  (VPC de Google)"
echo "  Red local  : $CORP_CIDR    (tu red interna)"
echo "  Shared Key : (la que definiste en variables.env)"
echo ""
echo "  Además, asegúrate de que mtapp05 permita conexiones"
echo "  MySQL (puerto 3306) desde el rango $SUBNET_CIDR"
echo "═══════════════════════════════════════════════════════"
