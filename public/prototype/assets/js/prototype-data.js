window.REBORN_DATA = {
  product: {
    detectedName: "Bosch Series 4 Dishwasher — Lower Basket Wheel",
    confidence: 0.87,
    repairDna: "RB-DNA-26-0042",
    category: "Home appliance / Dishwasher / Basket wheel assembly",
    status: "Repairable",
    risk: "Low",
    material: "PA12 or PETG-CF",
    dimensions: "Ø 36 mm × 12 mm",
    estimatedLife: "18–30 months"
  },
  repairPaths: [
    {
      id: "reuse",
      title: "Recover existing spare part",
      score: 82,
      cost: "€8–14",
      eta: "2–4 days",
      impact: "Lowest risk",
      recommendation: "Best when the original part is available nearby."
    },
    {
      id: "print",
      title: "Produce locally",
      score: 91,
      cost: "€11–19",
      eta: "24–48 h",
      impact: "Low CO₂, local production",
      recommendation: "Recommended MVP path: high availability and fast fulfilment."
    },
    {
      id: "ai",
      title: "Generate repair model with AI",
      score: 74,
      cost: "3 credits + print",
      eta: "48–72 h",
      impact: "Learns from new repairs",
      recommendation: "Use when no verified model exists. Requires validation."
    }
  ],
  providers: [
    { name: "Bologna 3D Lab", type: "Professional Service", distance: "3.8 km", rating: "4.9", jobs: 312, price: "€14.80", eta: "Tomorrow 17:00", trust: 96, material: "PETG-CF" },
    { name: "Maker Marco", type: "Independent Maker", distance: "6.2 km", rating: "4.8", jobs: 86, price: "€11.40", eta: "48 h", trust: 89, material: "PETG" },
    { name: "FabLab Nord", type: "Community Lab", distance: "12 km", rating: "4.7", jobs: 151, price: "€13.20", eta: "2 days", trust: 92, material: "PA12" }
  ],
  wallet: {
    credits: 12,
    pendingRoyalties: "€38.40",
    savedObjects: 7,
    co2: "18.4 kg"
  },
  events: [
    ["09:12", "Recognition completed", "Product family and component category identified with 87% confidence."],
    ["09:14", "Knowledge Graph matched", "3 existing repair paths found, 1 verified CAD model available."],
    ["09:18", "Provider quotes generated", "Local provider network returned 3 viable production options."],
    ["09:21", "Repair plan ready", "Recommended path: local production with verified model."],
  ]
};
