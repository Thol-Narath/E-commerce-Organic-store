export default function BrandLogosStrip() {
  const brands = [
    { name: 'USDA Organic', label: 'USDA' },
    { name: 'EU Organic', label: 'EU BIO' },
    { name: 'EcoCert', label: 'ECO' },
    { name: 'Vegan Society', label: 'VEGAN' },
    { name: 'Non-GMO', label: 'Non-GMO' },
    { name: 'Fair Trade', label: 'FAIR' },
  ];

  return (
    <section className="section-brands py-4 border-top border-bottom">
      <div className="container-lg">
        <div className="brands-strip">
          {brands.map((brand) => (
            <div key={brand.label} className="brand-item">
              <span className="brand-logo-text">{brand.label}</span>
              <span className="brand-name-text">{brand.name}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
