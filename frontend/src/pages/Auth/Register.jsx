import React, { useState } from "react";
import Input from "../../components/Common/Input";

const Register = () => {
  const [formData, setFormData] = useState({
    pseudo: "",
    email: "",
    password: "",
  });

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    // TODO: Connecter plus tard avec l'API Symfony POST /api/register
    console.log("Données d'inscription soumises :", formData);
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border border-slate-100">
        <div className="text-center mb-8">
          <h2 className="text-3xl font-bold text-slate-900">
            Rejoindre <span className="text-orange-500">Mon Allure</span>
          </h2>
          <p className="text-sm text-slate-500 mt-2">
            Crée ton compte pour générer ton plan personnalisé
          </p>
        </div>

        <form onSubmit={handleSubmit}>
          <Input
            label="Pseudo"
            name="pseudo"
            value={formData.pseudo}
            onChange={handleChange}
            placeholder="Ex: Runner81"
            required
          />
          <Input
            label="Adresse email"
            type="email"
            name="email"
            value={formData.email}
            onChange={handleChange}
            placeholder="votre@email.com"
            required
          />
          <Input
            label="Mot de passe"
            type="password"
            name="password"
            value={formData.password}
            onChange={handleChange}
            placeholder="••••••••"
            required
          />

          <button
            type="submit"
            className="w-full mt-4 bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 text-sm"
          >
            S'inscrire
          </button>
        </form>
      </div>
    </div>
  );
};

export default Register;
